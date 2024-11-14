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
        SessionManagerInterface $sessionManager
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

    protected function makeRequest(string $url, string $token = ''): array
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
                    if (!empty($option['value'])) {
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
                'backend_table' => $attribute->getBackendTable(),
                'attribute_id' => $attribute->getAttributeId(),
                'is_configurable' => $attribute->getIsConfigurable(),
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

    protected function prepareProductCollection($collection, $productIds)
    {
        $collection
            ->addAttributeToSelect('*')
            ->addIdFilter($productIds)
            ->addAttributeToSelect('type_id')
            ->addStoreFilter()
            ->addWebsiteFilter();

        // Add visibility handling for both simple and configurable
        $collection->setVisibility([
            \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_CATALOG,
            \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_SEARCH,
            \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH,
        ]);

        return $collection;
    }

    protected function getParentIds(array $productIds): array
    {
        try {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $productTypeConfigurable = $objectManager->get(
                \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable::class
            );

            return array_unique(
                $productTypeConfigurable->getParentIdsByChild($productIds)
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'Error getting parent IDs: ' . $e->getMessage()
            );
            return [];
        }
    }

    protected function getAllRelatedProductIds(array $productIds): array
    {
        $parentIds = $this->getParentIds($productIds);
        return array_unique(array_merge($productIds, $parentIds));
    }

    protected function enhanceCollectionWithConfigurables($collection)
    {
        $collection->joinTable(
            ['link_table' => 'catalog_product_super_link'],
            'product_id = e.entity_id',
            ['parent_id'],
            null,
            'left'
        );

        $collection->joinTable(
            ['parent' => 'catalog_product_entity'],
            'entity_id = link_table.parent_id',
            ['parent_type_id' => 'type_id'],
            null,
            'left'
        );

        // Add configurable product attributes
        $collection->joinTable(
            ['super_attribute' => 'catalog_product_super_attribute'],
            'product_id = link_table.parent_id',
            ['attribute_id'],
            null,
            'left'
        );

        return $collection;
    }

    protected function joinConfigurableAttribute($collection, $code, $attribute)
    {
        $connection = $collection->getConnection();
        $tableAlias = $code . '_table';
        $storeId = $collection->getStoreId();

        // Join child product values - store specific
        $collection->joinTable(
            ['child_' . $tableAlias => $attribute['backend_table']],
            'entity_id = link_table.product_id',
            [
                $code . '_child' => 'value',
            ],
            [
                'attribute_id = ?' => $attribute['attribute_id'],
                'store_id = ?' => $storeId,
            ],
            'left'
        );

        // Join child product default values
        $collection->joinTable(
            [
                'child_' . $tableAlias . '_default' => $attribute[
                    'backend_table'
                ],
            ],
            'entity_id = link_table.product_id',
            [
                $code . '_child_default' => 'value',
            ],
            [
                'attribute_id = ?' => $attribute['attribute_id'],
                'store_id = 0',
            ],
            'left'
        );

        // Join parent product values - store specific
        $collection->joinTable(
            ['parent_' . $tableAlias => $attribute['backend_table']],
            'entity_id = e.entity_id',
            [
                $code . '_parent' => 'value',
            ],
            [
                'attribute_id = ?' => $attribute['attribute_id'],
                'store_id = ?' => $storeId,
            ],
            'left'
        );

        // Join parent product default values
        $collection->joinTable(
            [
                'parent_' . $tableAlias . '_default' => $attribute[
                    'backend_table'
                ],
            ],
            'entity_id = e.entity_id',
            [
                $code . '_parent_default' => 'value',
            ],
            [
                'attribute_id = ?' => $attribute['attribute_id'],
                'store_id = 0',
            ],
            'left'
        );

        // Use COALESCE to get the correct value following the fallback logic
        $collection->getSelect()->columns([
            $code => new \Zend_Db_Expr("COALESCE(
            child_{$tableAlias}.value,
            child_{$tableAlias}_default.value,
            parent_{$tableAlias}.value,
            parent_{$tableAlias}_default.value
        )"),
        ]);

        return $collection;
    }

    protected function processConfigurableAttributes(
        $product,
        $filterableAttributes
    ) {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $productTypeInstance = $objectManager->get(
            \Magento\ConfigurableProduct\Model\Product\Type\Configurable::class
        );

        if (
            $product->getTypeId() ===
            \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE
        ) {
            $configurableAttributes = $productTypeInstance->getConfigurableAttributes(
                $product
            );
            foreach ($configurableAttributes as $attribute) {
                $attributeCode = $attribute
                    ->getProductAttribute()
                    ->getAttributeCode();
                if (isset($filterableAttributes[$attributeCode])) {
                    $filterableAttributes[$attributeCode][
                        'is_configurable'
                    ] = true;
                }
            }
        }

        return $filterableAttributes;
    }

    protected function createAttributeBucket($code, $attribute, $products)
    {
        $counts = [];

        foreach ($products as $product) {
            $value = $product[$code] ?? null;

            // Handle possible array values from configurable products
            if (is_array($value)) {
                foreach ($value as $v) {
                    if (!isset($counts[$v])) {
                        $counts[$v] = 0;
                    }
                    $counts[$v]++;
                }
            } elseif ($value !== null) {
                if (!isset($counts[$value])) {
                    $counts[$value] = 0;
                }
                $counts[$value]++;
            }
        }

        $values = [];
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

        return new \Magento\Framework\Search\Response\Bucket(
            $code . self::BUCKET_SUFFIX,
            $values
        );
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
            // Test direct product load first
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $productRepository = $objectManager->get(
                \Magento\Catalog\Api\ProductRepositoryInterface::class
            );

            $queryParams = $this->getQueryParams($request);

            // Get product IDs
            $productIds = $this->getProductIds($queryParams);

            if (empty($productIds)) {
                // Return empty response with proper structure for WeltPixel
                return new QueryResponse([], new Aggregation([]), 0);
            }

            // Get all related product IDs (including parents)
            $allProductIds = $this->getAllRelatedProductIds($productIds);

            // Get filterable attributes
            $filterableAttributes = $this->getFilterableAttributes();

            // Create and prepare collection
            $collection = $this->productCollectionFactory->create();
            $collection = $this->prepareProductCollection(
                $collection,
                $allProductIds
            );

            // Enhance collection with configurable products data
            $collection = $this->enhanceCollectionWithConfigurables(
                $collection
            );

            // Join attributes
            foreach ($filterableAttributes as $code => $attribute) {
                if ($attribute['is_configurable']) {
                    $this->joinConfigurableAttribute(
                        $collection,
                        $code,
                        $attribute
                    );
                } else {
                    $collection->joinAttribute(
                        $code,
                        'catalog_product/' . $code,
                        'entity_id',
                        null,
                        'left'
                    );
                }
            }

            // Collect products
            $products = [];
            foreach ($collection as $product) {
                // Update filterable attributes with configurable information
                $filterableAttributes = $this->processConfigurableAttributes(
                    $product,
                    $filterableAttributes
                );

                $productData = [
                    'entity_id' => $product->getId(),
                    'name' => $product->getName(),
                    'price' => (float) $product->getPrice(),
                    'sku' => $product->getSku(),
                    'type_id' => $product->getTypeId(),
                    'category_ids' => array_map(
                        'intval',
                        $product->getCategoryIds()
                    ),
                ];

                foreach ($filterableAttributes as $code => $attribute) {
                    $value = $product->getData($code);
                    if ($value !== null) {
                        $productData[$code] = $value;
                    }
                }

                $products[] = $productData;
            }

            // Create documents with WeltPixel compatibility
            $documents = [];
            foreach ($products as $product) {
                $attributes = [
                    'entity_id' => new Value(
                        $product['entity_id'],
                        'entity_id'
                    ),
                    'score' => new Value(1, 'score'), // Required by WeltPixel
                    '_score' => new Value(1, '_score'), // Required by WeltPixel
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

            $buckets['price_bucket'] = $this->createPriceBucket($products);

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

            // Create buckets for each filterable attribute
            foreach ($filterableAttributes as $code => $attribute) {
                if ($code === 'price') {
                    continue;
                }

                if ($attribute['is_configurable']) {
                    $buckets[
                        $code . self::BUCKET_SUFFIX
                    ] = $this->createAttributeBucket(
                        $code,
                        $attribute,
                        $products
                    );
                } else {
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
            }

            // Create aggregations and response
            $aggregations = new Aggregation($buckets);
            $response = new QueryResponse(
                $documents,
                $aggregations,
                count($documents)
            );

            // Store buckets if _gsSearchId is present
            if (isset($queryParams['_gsSearchId'])) {
                $cacheKey = 'gp_buckets_' . $queryParams['_gsSearchId'];
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

            return $response;
        } catch (\Exception $e) {
            $this->logger->error(
                'SearchEnginePlugin Error: ' . $e->getMessage()
            );
            $this->logger->error($e->getTraceAsString());
            return $proceed($request); // Fallback to original search on error
        }
    }

    protected function getValueCounts(
        array $products,
        string $field,
        bool $isArray = false
    ): array {
        $counts = [];
        foreach ($products as $product) {
            // Special handling for Yes/No attributes
            if (array_key_exists($field, $product)) {
                // Use array_key_exists instead of isset
                if ($isArray || is_array($product[$field])) {
                    foreach ((array) $product[$field] as $value) {
                        if (!isset($counts[$value])) {
                            $counts[$value] = 0;
                        }
                        $counts[$value]++;
                    }
                } else {
                    $value = $product[$field];
                    // Ensure we count '0' values
                    if (!isset($counts[$value])) {
                        $counts[$value] = 0;
                    }
                    $counts[$value]++;
                }
            } else {
                // Count null/unset values as "No" (0)
                if (!isset($counts['0'])) {
                    $counts['0'] = 0;
                }
                $counts['0']++;
            }
        }
        return $counts;
    }

    protected function createPriceBucket($products)
    {
        // Keep original price ranges
        return new \Magento\Framework\Search\Response\Bucket('price_bucket', [
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
    }
}
