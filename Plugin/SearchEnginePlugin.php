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

        foreach ($queryParams as $code => $value) {
            if (
                empty($value) ||
                in_array($code, ['q', '_gsSearchId', 'gpSearchOverride'])
            ) {
                continue;
            }

            $jsonFilter[$code] = [
                [
                    'value' => $value,
                    'label' => $value,
                ],
            ];

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
                    // Changed from !empty() to isset() to include zero values
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

        $this->logger->debug('Raw category counts from products:', [
            'category_counts' => $categoryCounts,
            'total_products' => count($products),
        ]);

        // Get the parent category IDs that should appear in navigation (level 2)
        $categoryResource = $this->objectManager->get(
            \Magento\Catalog\Model\ResourceModel\Category::class
        );
        $connection = $categoryResource->getConnection();

        // Simplified query that should find all level 2 categories (parent categories)
        $select = $connection
            ->select()
            ->from(['e' => $categoryResource->getEntityTable()], ['entity_id'])
            ->where('e.level = ?', 2);
        $categoryIds = $connection->fetchCol($select);

        $this->logger->debug('All level 2 categories:', [
            'category_ids' => $categoryIds,
        ]);

        // Now for each level 2 category, check if it meets our criteria
        $validCategoryIds = [];
        foreach ($categoryIds as $categoryId) {
            // Check is_anchor attribute
            $isAnchorSelect = $connection
                ->select()
                ->from(
                    [
                        'a' => $categoryResource->getTable(
                            'catalog_category_entity_int'
                        ),
                    ],
                    ['value']
                )
                ->where('a.entity_id = ?', $categoryId)
                ->where(
                    'a.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = ? AND entity_type_id = 3)',
                    'is_anchor'
                );
            $isAnchor = $connection->fetchOne($isAnchorSelect);

            // Check include_in_menu attribute
            $includeInMenuSelect = $connection
                ->select()
                ->from(
                    [
                        'm' => $categoryResource->getTable(
                            'catalog_category_entity_int'
                        ),
                    ],
                    ['value']
                )
                ->where('m.entity_id = ?', $categoryId)
                ->where(
                    'm.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = ? AND entity_type_id = 3)',
                    'include_in_menu'
                );
            $includeInMenu = $connection->fetchOne($includeInMenuSelect);

            // Check is_active attribute
            $isActiveSelect = $connection
                ->select()
                ->from(
                    [
                        's' => $categoryResource->getTable(
                            'catalog_category_entity_int'
                        ),
                    ],
                    ['value']
                )
                ->where('s.entity_id = ?', $categoryId)
                ->where(
                    's.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = ? AND entity_type_id = 3)',
                    'is_active'
                );
            $isActive = $connection->fetchOne($isActiveSelect);

            // Get category name for logging
            $nameSelect = $connection
                ->select()
                ->from(
                    [
                        'n' => $categoryResource->getTable(
                            'catalog_category_entity_varchar'
                        ),
                    ],
                    ['value']
                )
                ->where('n.entity_id = ?', $categoryId)
                ->where(
                    'n.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = ? AND entity_type_id = 3)',
                    'name'
                );
            $name = $connection->fetchOne($nameSelect);

            $this->logger->debug("Checking category $categoryId ($name):", [
                'is_anchor' => $isAnchor,
                'include_in_menu' => $includeInMenu,
                'is_active' => $isActive,
            ]);

            if ($isAnchor == 1 && $includeInMenu == 1 && $isActive == 1) {
                $validCategoryIds[] = $categoryId;
                $this->logger->debug(
                    "Category $categoryId ($name) meets all criteria"
                );
            }
        }

        $this->logger->debug('Final valid category IDs matching criteria:', [
            'valid_categories' => $validCategoryIds,
        ]);

        // --- Aggregate counts from subcategories into their top-level (level 2) category
        $aggregatedCategoryCounts = [];
        foreach ($categoryCounts as $subCatId => $count) {
            // Retrieve the full category path for the subcategory.
            // The path is typically stored as a string like "1/2/10/25"
            $selectPath = $connection
                ->select()
                ->from($categoryResource->getEntityTable(), ['path'])
                ->where('entity_id = ?', $subCatId);
            $path = $connection->fetchOne($selectPath);

            if ($path) {
                $parts = explode('/', $path);
                // Index 0 is root, index 1 is default, index 2 is our level 2 category.
                if (isset($parts[2])) {
                    $topCatId = $parts[2];
                    if (!isset($aggregatedCategoryCounts[$topCatId])) {
                        $aggregatedCategoryCounts[$topCatId] = 0;
                    }
                    $aggregatedCategoryCounts[$topCatId] += (int) $count;
                }
            }
        }
        $this->logger->debug('Aggregated category counts:', [
            'aggregated_counts' => $aggregatedCategoryCounts,
        ]);

        // Check if we have any valid categories
        if (empty($validCategoryIds)) {
            $this->logger->warning(
                'No valid categories found matching layered navigation criteria'
            );
            $categoryValues[] = new Value(
                '0',
                [
                    'value' => '0',
                    'count' => 0,
                ],
                'category_bucket'
            );
        } else {
            // Create bucket values for all valid (level 2) categories using the aggregated counts,
            // but only add those with a count greater than zero.
            foreach ($validCategoryIds as $catId) {
                $count = isset($aggregatedCategoryCounts[$catId])
                    ? (int) $aggregatedCategoryCounts[$catId]
                    : 0;
                if ($count > 0) {
                    $categoryValues[] = new Value(
                        $catId,
                        [
                            'value' => $catId,
                            'count' => $count,
                        ],
                        'category_bucket'
                    );
                    $this->logger->debug('Added category to bucket:', [
                        'category_id' => $catId,
                        'count' => $count,
                        'has_products' => 'yes',
                    ]);
                } else {
                    $this->logger->debug('Skipping category (no products):', [
                        'category_id' => $catId,
                        'count' => $count,
                    ]);
                }
            }
        }

        // Create buckets in correct order
        $newBuckets = [];
        if (isset($buckets['price_bucket'])) {
            $newBuckets['price_bucket'] = $buckets['price_bucket'];
        }

        // Ensure we create all possible bucket names Magento might look for
        $categoryBucketNames = [
            'category_bucket',
            'category_filter',
            'category_id',
            'cat',
            'cat_id',
            'category',
            'category_ids',
            'category_ids_bucket',
        ];

        $this->logger->debug(
            'Creating buckets for all possible category names',
            [
                'bucket_names' => $categoryBucketNames,
            ]
        );

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
                !in_array($key, $categoryBucketNames)
            ) {
                $newBuckets[$key] = $bucket;
            }
        }

        $buckets = $newBuckets;

        $this->logger->debug('Final category buckets created', [
            'bucket_names' => array_keys($buckets),
            'category_values_count' => count($categoryValues),
            'category_ids_included' => array_map(function ($value) {
                return $value->getValue();
            }, $categoryValues),
        ]);
    }

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
            return $proceed($request);
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

            // foreach ($filterableAttributes as $code => $attribute) {
            //     $collection->joinAttribute(
            //         $code,
            //         'catalog_product/' . $code,
            //         'entity_id',
            //         null,
            //         'left'
            //     );
            // }

            $products = [];
            foreach ($collection as $product) {
                $categoryIds = $product->getCategoryIds();

                $productData = [
                    'entity_id' => $product->getId(),
                    'name' => $product->getName(),
                    'price' => (float) $product->getPrice(),
                    'sku' => $product->getSku(),
                    'category_ids' => array_map('intval', $categoryIds),
                ];

                foreach ($filterableAttributes as $code => $attribute) {
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

            // Create buckets array
            $buckets = [];

            // Price bucket
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

            // Category bucket
            $categoryValues = [];
            $categoryCounts = $this->getValueCounts(
                $products,
                'category_ids',
                true
            );

            foreach ($categoryCounts as $value => $count) {
                $valueMetrics = [
                    'value' => $value,
                    'count' => $count,
                ];

                $categoryValues[] = new Value(
                    (string) $value,
                    $valueMetrics,
                    'category_bucket'
                );
            }

            $buckets[
                'category_bucket'
            ] = new \Magento\Framework\Search\Response\Bucket(
                'category_bucket',
                $categoryValues
            );

            // Create buckets for filterable attributes
            foreach ($filterableAttributes as $code => $attribute) {
                if ($code === 'price') {
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

            $this->createCategoryBuckets($products, $buckets);

            $aggregations = new Aggregation($buckets);
            $response = new QueryResponse(
                $documents,
                $aggregations,
                count($documents)
            );

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

    /**
     * Get counts of attribute values across all products
     *
     * @param array $products Array of product data
     * @param string $field The attribute code to count values for
     * @param bool $isArray Whether the attribute is a multi-value attribute
     * @return array Associative array of value => count
     */
    protected function getValueCounts(
        array $products,
        string $field,
        bool $isArray = false
    ): array {
        $counts = [];
        foreach ($products as $product) {
            if (isset($product[$field])) {
                $values = is_array($product[$field])
                    ? $product[$field]
                    : [$product[$field]];

                foreach ($values as $value) {
                    if ($value !== null && $value !== '') {
                        // Split comma-separated values
                        $individualValues =
                            is_string($value) && strpos($value, ',') !== false
                                ? explode(',', $value)
                                : [$value];

                        foreach ($individualValues as $indValue) {
                            $indValue = trim($indValue); // Trim whitespace
                            if ($indValue !== null && $indValue !== '') {
                                if (!isset($counts[$indValue])) {
                                    $counts[$indValue] = 0;
                                }
                                $counts[$indValue]++;
                            }
                        }
                    }
                }
            }
        }

        $this->logger->debug('Value counts computed:', [
            'field' => $field,
            'counts' => $counts,
            'product_count' => count($products),
        ]);

        return $counts;
    }
}
