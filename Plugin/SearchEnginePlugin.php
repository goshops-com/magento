<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\Response\QueryResponse;
use Magento\Framework\Search\Response\Aggregation;
use Magento\Framework\Search\Response\Aggregation\Value;
use Magento\Framework\Api\Search\Document;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\RequestInterface as HttpRequestInterface;
use Magento\Search\Model\SearchEngine;
use Magento\Framework\ObjectManagerInterface;
use Magento\Catalog\Model\Layer\Category\FilterableAttributeList;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

class SearchEnginePlugin
{
    const BUCKET_SUFFIX = '_bucket';

    protected $logger;
    protected $httpRequest;
    protected $objectManager;
    protected $productCollectionFactory;
    protected $cookieManager;
    protected $scopeConfig;
    protected $httpClient;
    protected $cache;
    protected $sessionManager;

    public function __construct(
        LoggerInterface $logger,
        HttpRequestInterface $httpRequest,
        ObjectManagerInterface $objectManager,
        FilterableAttributeList $filterableAttributeList,
        ProductCollectionFactory $productCollectionFactory,
        CookieManagerInterface $cookieManager,
        ScopeConfigInterface $scopeConfig,
        Curl $httpClient,
        \Magento\Framework\App\CacheInterface $cache,
        SessionManagerInterface $sessionManager,
        Configurable $configurableType
    ) {
        $this->logger = $logger;
        $this->httpRequest = $httpRequest;
        $this->objectManager = $objectManager;
        $this->filterableAttributeList = $filterableAttributeList;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->cookieManager = $cookieManager;
        $this->scopeConfig = $scopeConfig;
        $this->httpClient = $httpClient;
        $this->cache = $cache;
        $this->sessionManager = $sessionManager;
        $this->configurableType = $configurableType;
    }

    protected function getProductIds(array $queryParams): array
    {
        $maxAttempts = 2;
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            try {
                // Get JWT token and prepare session fallback flags
                $token = $this->cookieManager->getCookie('gopersonal_jwt');
                $useSessionFallback = false;

                if (!$token) {
                    $this->logger->debug(
                        'No JWT token found, will use session fallback'
                    );
                    $useSessionFallback = true;
                }

                // Build base URL
                $clientId = $this->scopeConfig->getValue(
                    'gopersonal/general/client_id',
                    ScopeInterface::SCOPE_STORE
                );
                $baseUrl =
                    strpos($clientId, 'D-') === 0
                        ? 'https://go-discover-dev.goshops.ai'
                        : 'https://discover.gopersonal.ai';

                $url = $baseUrl . '/item/search?adapter=magento';
                $urlParams = $this->buildUrlParameters($queryParams);

                // Add session fallback parameters if no token
                if ($useSessionFallback) {
                    $urlParams = $this->addSessionFallbackParams(
                        $urlParams,
                        $clientId
                    );
                }

                // Make the request
                $finalUrl = $url . '&' . http_build_query($urlParams);
                $this->logger->debug('Making request to:', [
                    'url' => $finalUrl,
                    'using_session_fallback' => $useSessionFallback,
                ]);

                $response = $this->makeRequest($finalUrl, $token);

                // Handle 401 unauthorized error - retry with session fallback if not already using it
                if ($response['status'] === 401 && !$useSessionFallback) {
                    $this->logger->debug(
                        'Received 401, attempting with session fallback'
                    );
                    $urlParams = $this->addSessionFallbackParams(
                        $urlParams,
                        $clientId
                    );
                    $finalUrl = $url . '&' . http_build_query($urlParams);
                    $response = $this->makeRequest($finalUrl, $token);
                }

                if ($response['status'] === 200 && !empty($response['body'])) {
                    $result = json_decode($response['body'], true);
                    if (is_array($result)) {
                        $this->logger->debug('API response:', [
                            'result' => $result,
                        ]);
                        return $result;
                    }

                    $this->logger->error('Invalid response format:', [
                        'response' => $response['body'],
                    ]);
                }

                $attempts++;
                if ($attempts < $maxAttempts) {
                    $this->logger->debug(
                        "Retrying request, attempt {$attempts} of {$maxAttempts}"
                    );
                    continue;
                }

                return [];
            } catch (\Exception $e) {
                $this->logger->error(
                    'Error getting product IDs: ' . $e->getMessage()
                );
                $this->logger->error($e->getTraceAsString());

                $attempts++;
                if ($attempts >= $maxAttempts) {
                    return [];
                }
            }
        }

        return [];
    }

    protected function buildUrlParameters(array $queryParams): array
    {
        $urlParams = [];
        $gsSearchId = $queryParams['_gsSearchId'] ?? null;

        // Add search term if exists
        if (isset($queryParams['q'])) {
            $urlParams['query'] = $queryParams['q'];
            unset($queryParams['q']);
        }

        // Handle filters
        if (
            !empty(
                array_diff_key($queryParams, [
                    '_gsSearchId' => '',
                    'gpSearchOverride' => '',
                ])
            )
        ) {
            $jsonFilter = $this->processFilters(
                $queryParams,
                $gsSearchId,
                $urlParams
            );
            if (!empty($jsonFilter)) {
                $urlParams['jsonFilter'] = json_encode($jsonFilter);
            }
        }

        // Add gsSearchId if exists
        if ($gsSearchId) {
            $urlParams['_gsSearchId'] = $gsSearchId;
        }

        return $urlParams;
    }

    protected function processFilters(
        array $queryParams,
        ?string $gsSearchId,
        array &$urlParams
    ): array {
        $jsonFilter = [];
        $storedBuckets = $this->getStoredBuckets($gsSearchId);

        // Log all filter parameters for debugging
        $this->logger->debug('Processing filters with params:', [
            'query_params' => $queryParams,
        ]);

        foreach ($queryParams as $code => $value) {
            if (
                empty($value) ||
                in_array($code, ['q', '_gsSearchId', 'gpSearchOverride'])
            ) {
                continue;
            }

            // Special handling for any category-related parameter names
            if (
                in_array($code, [
                    'category_id',
                    'cat_id',
                    'category',
                    'category_filter',
                    'category_ids',
                ])
            ) {
                $this->logger->debug('Processing category filter:', [
                    'code' => $code,
                    'value' => $value,
                ]);

                // Ensure we use a consistent key for category filters
                $jsonFilter['category_ids'] = [
                    [
                        'value' => $value,
                        'label' => $value,
                    ],
                ];
            } else {
                $jsonFilter[$code] = [
                    [
                        'value' => $value,
                        'label' => $value,
                    ],
                ];
            }

            if ($storedBuckets) {
                $this->processBucketLimit(
                    $code,
                    $value,
                    $storedBuckets,
                    $urlParams
                );
            }
        }

        return $jsonFilter;
    }

    public function debugLayeredNavigation()
    {
        try {
            // Get a reference to the layer filter factory
            $filterList = $this->objectManager->get(
                \Magento\Catalog\Model\Layer\FilterList::class
            );
            $layer = $this->objectManager->get(
                \Magento\Catalog\Model\Layer\Category::class
            );

            $filters = $filterList->getFilters($layer);
            $filterInfo = [];

            foreach ($filters as $filter) {
                $filterInfo[] = [
                    'class' => get_class($filter),
                    'request_var' => method_exists($filter, 'getRequestVar')
                        ? $filter->getRequestVar()
                        : 'unknown',
                    'filter_items_count' => method_exists(
                        $filter,
                        'getItemsCount'
                    )
                        ? $filter->getItemsCount()
                        : 0,
                ];
            }

            $this->logger->debug('Available layered navigation filters:', [
                'filters' => $filterInfo,
            ]);
        } catch (\Exception $e) {
            $this->logger->error(
                'Error in debug layered navigation: ' . $e->getMessage()
            );
        }
    }

    protected function getStoredBuckets(?string $gsSearchId): ?array
    {
        if (!$gsSearchId) {
            return null;
        }

        $cacheKey = 'gp_buckets_' . $gsSearchId;
        $storedBuckets = $this->cache->load($cacheKey);
        $decodedBuckets = json_decode($storedBuckets, true);

        $this->logger->debug('Retrieved bucket data:', [
            'raw' => $storedBuckets,
            'decoded' => $decodedBuckets,
        ]);

        return $decodedBuckets;
    }

    protected function processBucketLimit(
        string $code,
        $value,
        array $storedBuckets,
        array &$urlParams
    ): void {
        $bucketKey = $code . '_bucket';
        if (isset($storedBuckets[$bucketKey]['values'])) {
            foreach ($storedBuckets[$bucketKey]['values'] as $bucketValue) {
                if ((string) $bucketValue['value'] === (string) $value) {
                    $urlParams['limit'] = $bucketValue['metrics']['count'];
                    $this->logger->debug("Setting limit for $code", [
                        'value' => $value,
                        'limit' => $bucketValue['metrics']['count'],
                    ]);
                    break;
                }
            }
        }
    }

    protected function makeRequest(string $url, ?string $token = null): array
    {
        if ($token) {
            $this->httpClient->addHeader('Authorization', 'Bearer ' . $token);
        }
        $this->httpClient->addHeader('Content-Type', 'application/json');
        $this->httpClient->get($url);

        return [
            'status' => $this->httpClient->getStatus(),
            'body' => $this->httpClient->getBody(),
        ];
    }

    protected function addSessionFallbackParams(
        array $urlParams,
        string $clientId
    ): array {
        $sessionId = $this->sessionManager->getSessionId();
        $urlParams['externalSessionId'] = $sessionId;
        $urlParams['clientId'] = $clientId;
        $urlParams['sessionFallback'] = 'true';

        return $urlParams;
    }

    protected function getFilterableAttributes()
    {
        $attributes = [];
        foreach ($this->filterableAttributeList->getList() as $attribute) {
            $code = $attribute->getAttributeCode();
            $options = [];
            if ($attribute->usesSource()) {
                foreach ($attribute->getSource()->getAllOptions() as $option) {
                    if (isset($option['value']) && $option['value'] !== '') {
                        $options[$option['value']] = [
                            'value' => $option['value'],
                            'label' => $option['label'],
                        ];
                    }
                }
            }
            $attributes[$code] = [
                'code' => $code,
                'frontend_label' => $attribute->getFrontendLabel(),
                'backend_type' => $attribute->getBackendType(),
                'frontend_input' => $attribute->getFrontendInput(),
                'source_model' => $attribute->getSourceModel(),
                'options' => $options,
            ];
        }
        // Ensure that category_ids is defined and has options.
        if (
            !isset($attributes['category_ids']) ||
            empty($attributes['category_ids']['options'])
        ) {
            $this->logger->debug('Loading category options for category_ids');
            try {
                $categoryCollectionFactory = $this->objectManager->get(
                    \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory::class
                );
                $categoryCollection = $categoryCollectionFactory
                    ->create()
                    ->addAttributeToSelect('name')
                    ->addAttributeToFilter('is_active', 1)
                    ->setOrder('position', 'ASC');
                $categoryOptions = [];
                foreach ($categoryCollection as $category) {
                    $categoryOptions[$category->getId()] = [
                        'value' => $category->getId(),
                        'label' => $category->getName(),
                    ];
                }
                $attributes['category_ids'] = [
                    'code' => 'category_ids',
                    'frontend_label' => 'Categories',
                    'backend_type' => 'static',
                    'frontend_input' => 'multiselect',
                    'source_model' => null,
                    'options' => $categoryOptions,
                ];
                $this->logger->debug('Category options loaded', [
                    'options' => $categoryOptions,
                ]);
            } catch (\Exception $e) {
                $this->logger->error(
                    'Error loading category options: ' . $e->getMessage()
                );
            }
        }
        return $attributes;
    }

    protected function getQueryParams(RequestInterface $request): array
    {
        // Read all query parameters from the request
        $queryParams = $this->httpRequest->getParams();

        $this->logger->debug('Raw query params:', $queryParams);

        // Get the search term from the request
        if (isset($queryParams['q'])) {
            // Initialize filters array
            $filters = $queryParams;
            unset($filters['q']); // Remove 'q' parameter since we handle it separately

            // Log the parameters we'll send
            $this->logger->debug('Search parameters:', [
                'query' => $queryParams['q'],
                'filters' => $filters,
            ]);

            return $queryParams;
        }

        return [];
    }

    protected function getProductAttributeValues($product, $attributeCode)
    {
        $values = [];

        // If it's a configurable product, get values from all child products
        if ($product->getTypeId() === Configurable::TYPE_CODE) {
            $childProducts = $this->configurableType->getUsedProducts($product);
            foreach ($childProducts as $childProduct) {
                $value = $childProduct->getData($attributeCode);
                if ($value !== null) {
                    $values[] = $value;
                }
            }
            // Also include the configurable product's own value if it exists
            $parentValue = $product->getData($attributeCode);
            if ($parentValue !== null) {
                $values[] = $parentValue;
            }
        } else {
            // For simple products, just get the direct value
            $value = $product->getData($attributeCode);
            if ($value !== null) {
                $values[] = $value;
            }
        }

        // Remove duplicates and return
        return array_unique($values);
    }

    protected function storeBucketsInCache(
        array $buckets,
        string $gsSearchId
    ): void {
        $cacheKey = 'gp_buckets_' . $gsSearchId;
        $bucketsToStore = [];

        foreach ($buckets as $code => $bucket) {
            $values = [];
            foreach ($bucket->getValues() as $value) {
                $values[] = [
                    'value' => $value->getValue(),
                    'metrics' => $value->getMetrics(),
                    'aggregation' => $bucket->getName(),
                ];
            }
            $bucketsToStore[$code] = [
                'name' => $bucket->getName(),
                'values' => $values,
            ];
        }

        $bucketsJson = json_encode($bucketsToStore);
        $this->cache->save($bucketsJson, $cacheKey, [], 3600);
    }

    protected function createCategoryBuckets(
        array $products,
        array &$buckets
    ): void {
        $categoryValues = [];
        $categoryCounts = $this->getValueCounts(
            $products,
            'category_ids',
            true
        );

        // Get only the parent category IDs that should appear in navigation
        $categoryResource = $this->objectManager->get(
            \Magento\Catalog\Model\ResourceModel\Category::class
        );
        $connection = $categoryResource->getConnection();

        // Query to get parent categories with is_anchor=1 and include_in_menu=1
        $select = $connection
            ->select()
            ->from(['e' => $categoryResource->getEntityTable()], ['entity_id'])
            ->joinLeft(
                [
                    'a' => $categoryResource->getTable(
                        'catalog_category_entity_int'
                    ),
                ],
                "e.entity_id = a.entity_id AND a.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'is_anchor' AND entity_type_id = 3)",
                []
            )
            ->joinLeft(
                [
                    'i' => $categoryResource->getTable(
                        'catalog_category_entity_int'
                    ),
                ],
                "e.entity_id = i.entity_id AND i.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'include_in_menu' AND entity_type_id = 3)",
                []
            )
            ->where('a.value = ?', 1)
            ->where('i.value = ?', 1)
            ->where('e.level <= ?', 2); // Only parent categories (level 2 or less)

        $parentCategoryIds = $connection->fetchCol($select);

        $this->logger->debug('Parent category IDs for filtering:', [
            'parent_categories' => $parentCategoryIds,
        ]);

        // Get counts for parent categories only
        $parentCategoryCounts = [];
        foreach ($categoryCounts as $catId => $count) {
            $intCatId = (int) $catId;
            // Include only parent categories
            if (in_array($intCatId, $parentCategoryIds)) {
                $parentCategoryCounts[$intCatId] = $count;
            }
        }

        // If no parent categories found, don't create category buckets
        if (empty($parentCategoryCounts)) {
            $this->logger->debug(
                'No parent categories found, skipping category bucket creation'
            );
            return;
        }

        // Create bucket values for parent categories
        foreach ($parentCategoryCounts as $catId => $count) {
            $categoryValues[] = new Value(
                $catId,
                [
                    'value' => $catId,
                    'count' => (int) $count,
                ],
                'category_bucket'
            );
        }

        // Create buckets in correct order
        $newBuckets = [];
        if (isset($buckets['price_bucket'])) {
            $newBuckets['price_bucket'] = $buckets['price_bucket'];
        }

        $newBuckets[
            'category_bucket'
        ] = new \Magento\Framework\Search\Response\Bucket(
            'category_bucket',
            $categoryValues
        );

        // Also add compatibility bucket names
        $categoryBucketNames = [
            'category_filter',
            'category_id',
            'cat_id',
            'category_ids_bucket',
            'category',
            'category_ids',
        ];

        foreach ($categoryBucketNames as $name) {
            $newBuckets[$name] = new \Magento\Framework\Search\Response\Bucket(
                $name,
                $categoryValues
            );
        }

        // Add remaining buckets
        foreach ($buckets as $key => $bucket) {
            if (
                $key !== 'price_bucket' &&
                $key !== 'category_bucket' &&
                !in_array($key, $categoryBucketNames)
            ) {
                $newBuckets[$key] = $bucket;
            }
        }

        $buckets = $newBuckets;

        $this->logger->debug(
            'Final category buckets created with parent categories only',
            [
                'bucket_names' => array_keys($buckets),
                'category_values_count' => count($categoryValues),
            ]
        );
    }

    /**
     * Process search results in aroundSearch - keeping most code but changing the bucket creation order
     */
    public function aroundSearch(
        SearchEngine $subject,
        callable $proceed,
        RequestInterface $request
    ) {
        $this->logger->debug('SearchEnginePlugin aroundSearch() called');

        $pathInfo = $this->httpRequest->getPathInfo();
        if (strpos($pathInfo, '/catalogsearch/result') === false) {
            return $proceed($request);
        }

        // Always call the original method and log its response structure
        $originalResponse = $proceed($request);

        // Debug the original response
        if ($originalResponse instanceof QueryResponse) {
            $originalBuckets = [];
            foreach (
                $originalResponse->getAggregations()->getBuckets()
                as $bucket
            ) {
                $originalBuckets[] = $bucket->getName();
            }

            $this->logger->debug('Original Magento search response:', [
                'aggregation_bucket_count' => count($originalBuckets),
                'buckets' => $originalBuckets,
            ]);

            // Look specifically for category buckets
            foreach (
                $originalResponse->getAggregations()->getBuckets()
                as $bucket
            ) {
                if (strpos($bucket->getName(), 'cat') !== false) {
                    $values = [];
                    foreach ($bucket->getValues() as $value) {
                        $values[] = [
                            'value' => $value->getValue(),
                            'metrics' => $value->getMetrics(),
                        ];
                    }

                    $this->logger->debug(
                        'Original category bucket: ' . $bucket->getName(),
                        [
                            'value_count' => count($values),
                            'values' => $values,
                        ]
                    );
                }
            }
        }

        // Check if the override_catalog_search setting is enabled
        $isOverrideEnabled = $this->scopeConfig->getValue(
            'gopersonal/general/override_catalog_search',
            ScopeInterface::SCOPE_STORE
        );

        // If override is not enabled, use default search
        if (!$isOverrideEnabled) {
            $this->logger->debug(
                'SearchEnginePlugin: Custom search is disabled in configuration'
            );
            return $originalResponse;
        }

        $this->logger->debug('SearchEnginePlugin: USING CUSTOM SEARCH ENGINE');

        try {
            $queryParams = $this->getQueryParams($request);
            $productIds = $this->getProductIds($queryParams);
            $filterableAttributes = $this->getFilterableAttributes();

            $collection = $this->productCollectionFactory->create();

            // Add the basic filters
            $collection
                ->addAttributeToSelect('*')
                ->addIdFilter($productIds)
                ->addStoreFilter()
                ->addWebsiteFilter();

            // Add ORDER BY FIELD to maintain original order
            if (!empty($productIds)) {
                $connection = $collection->getConnection();
                $orderByField = new \Zend_Db_Expr(
                    sprintf('FIELD(e.entity_id, %s)', implode(',', $productIds))
                );
                $collection->getSelect()->order($orderByField);
            }

            // Join attributes but handle category_ids separately
            foreach ($filterableAttributes as $code => $attribute) {
                if ($code === 'category_ids') {
                    continue; // We'll handle category_ids differently
                }
                $collection->joinAttribute(
                    $code,
                    'catalog_product/' . $code,
                    'entity_id',
                    null,
                    'left'
                );
            }

            $products = [];
            foreach ($collection as $product) {
                // Always get category IDs for every product
                $categoryIds = $product->getCategoryIds();

                $this->logger->debug(
                    'Category IDs for product ' . $product->getId() . ':',
                    [
                        'category_ids' => $categoryIds,
                    ]
                );

                $productData = [
                    'entity_id' => $product->getId(),
                    'name' => $product->getName(),
                    'price' => (float) $product->getPrice(),
                    'sku' => $product->getSku(),
                    'category_ids' => array_map('intval', $categoryIds), // Always include category_ids
                ];

                foreach ($filterableAttributes as $code => $attribute) {
                    if ($code === 'category_ids') {
                        continue;
                    }
                    $values = $this->getProductAttributeValues($product, $code);
                    if (!empty($values)) {
                        $productData[$code] =
                            count($values) === 1 ? reset($values) : $values;
                    }
                }

                $products[] = $productData;

                $this->logger->debug('Processed product data:', [
                    'product_id' => $product->getId(),
                    'type' => $product->getTypeId(),
                    'category_ids' => $categoryIds,
                    'attributes' => array_intersect_key(
                        $productData,
                        $filterableAttributes
                    ),
                ]);
            }

            // Create documents
            $documents = [];
            foreach ($products as $product) {
                $this->logger->debug(
                    'Processing product for document creation:',
                    [
                        'product_id' => $product['entity_id'],
                    ]
                );

                $attributes = [
                    '_id' => new Value($product['entity_id'], '_id'),
                    '_score' => new Value(1, '_score'),
                    'entity_id' => new Value(
                        $product['entity_id'],
                        'entity_id'
                    ),
                    'score' => new Value(1, 'score'),
                    'name' => new Value($product['name'], 'name'),
                    'price' => new Value($product['price'], 'price'),
                    'sku' => new Value($product['sku'], 'sku'),
                    'status' => new Value(1, 'status'),
                    'visibility' => new Value(4, 'visibility'),
                    'store_id' => new Value(1, 'store_id'),
                    'category_ids' => new Value(
                        implode(',', $product['category_ids']),
                        'category_ids'
                    ),
                ];

                foreach ($filterableAttributes as $code => $attribute) {
                    if ($code === 'category_ids') {
                        continue;
                    }
                    if (isset($product[$code])) {
                        $value = is_array($product[$code])
                            ? implode(',', $product[$code])
                            : $product[$code];
                        $attributes[$code] = new Value($value, $code);
                    }
                }

                $document = new Document();
                $document->setId($product['entity_id']);
                $document->setCustomAttributes($attributes);
                $documents[] = $document;
            }

            // Create buckets array - start with an empty array
            $buckets = [];

            // Price bucket must be first
            $buckets[
                'price_bucket'
            ] = new \Magento\Framework\Search\Response\Bucket('price_bucket', [
                new Value(
                    '90_100',
                    [
                        'from' => 90,
                        'to' => 100,
                        'count' => 1,
                        'value' => '90_100',
                    ],
                    'price_bucket'
                ),
                new Value(
                    '140_150',
                    [
                        'from' => 140,
                        'to' => 150,
                        'count' => 1,
                        'value' => '140_150',
                    ],
                    'price_bucket'
                ),
            ]);

            // Category bucket must be second
            $this->createCategoryBuckets($products, $buckets);

            // Create buckets for other filterable attributes
            foreach ($filterableAttributes as $code => $attribute) {
                if ($code === 'price' || $code === 'category_ids') {
                    continue;
                }

                $counts = $this->getValueCounts(
                    $products,
                    $code,
                    $attribute['frontend_input'] === 'multiselect'
                );

                $values = [];
                if (!empty($counts)) {
                    foreach ($counts as $value => $count) {
                        $optionLabel = isset($attribute['options'][$value])
                            ? $attribute['options'][$value]['label']
                            : $value;

                        $values[] = new Value(
                            (string) $value,
                            [
                                'value' => $value,
                                'label' => $optionLabel,
                                'count' => $count,
                            ],
                            $code . self::BUCKET_SUFFIX
                        );
                    }
                }

                $buckets[
                    $code . self::BUCKET_SUFFIX
                ] = new \Magento\Framework\Search\Response\Bucket(
                    $code . self::BUCKET_SUFFIX,
                    $values
                );
            }

            // Log all bucket names
            $this->logger->debug('All bucket names:', [
                'buckets' => array_keys($buckets),
            ]);

            $aggregations = new Aggregation($buckets);
            $response = new QueryResponse(
                $documents,
                $aggregations,
                count($documents)
            );

            // Log the final response structure
            $this->logger->debug('QueryResponse structure:', [
                'document_count' => count($documents),
                'aggregation_buckets' => array_keys($buckets),
                'response_class' => get_class($response),
            ]);

            // Store buckets in cache if needed
            if (isset($queryParams['_gsSearchId'])) {
                $this->storeBucketsInCache(
                    $buckets,
                    $queryParams['_gsSearchId']
                );
            }

            return $response;
        } catch (\Exception $e) {
            $this->logger->error(
                'SearchEnginePlugin Error: ' . $e->getMessage()
            );
            $this->logger->error($e->getTraceAsString());
            return $proceed($request);
        }
    }

    protected function getValueCounts(
        array $products,
        string $field,
        bool $isArray = false
    ): array {
        $counts = [];
        foreach ($products as $product) {
            if (isset($product[$field])) {
                // Handle different scenarios for array values
                $values = [];

                // Handle the case where the value is already a comma-separated string
                if (
                    !is_array($product[$field]) &&
                    strpos($product[$field], ',') !== false
                ) {
                    $values = array_map('trim', explode(',', $product[$field]));
                }
                // Handle the case where it's already an array
                elseif (is_array($product[$field])) {
                    $values = $product[$field];
                }
                // Handle single value
                else {
                    $values = [$product[$field]];
                }

                foreach ($values as $value) {
                    if ($value !== null && $value !== '') {
                        // Trim spaces that might occur from explode
                        $value = trim($value);
                        if (!isset($counts[$value])) {
                            $counts[$value] = 0;
                        }
                        $counts[$value]++;
                    }
                }
            }
        }

        // Add special logging for category_ids
        if ($field === 'category_ids') {
            $this->logger->debug('Detailed category counts:', [
                'counts' => $counts,
                'products_processed' => count($products),
                'category_17_count' => $counts[17] ?? 'not found',
            ]);
        }

        $this->logger->debug('Value counts computed:', [
            'field' => $field,
            'counts' => $counts,
            'product_count' => count($products),
        ]);

        return $counts;
    }
}
