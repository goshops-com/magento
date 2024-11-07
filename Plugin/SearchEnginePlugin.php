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

    public function __construct(
        LoggerInterface $logger,
        HttpRequestInterface $httpRequest,
        ObjectManagerInterface $objectManager,
        FilterableAttributeList $filterableAttributeList, 
        ProductCollectionFactory $productCollectionFactory,
        CookieManagerInterface $cookieManager,
        ScopeConfigInterface $scopeConfig,
        Curl $httpClient,
        \Magento\Framework\App\CacheInterface $cache
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
    }

    protected function getProductIds(array $queryParams, $gsSearchId = null): array 
    {
        try {
            $token = $this->cookieManager->getCookie('gopersonal_jwt');
            if (!$token) {
                $this->logger->debug("No JWT token found");
                return [];
            }

            $clientId = $this->scopeConfig->getValue('gopersonal/general/client_id', ScopeInterface::SCOPE_STORE);
            $baseUrl = strpos($clientId, 'D-') === 0 
                ? 'https://go-discover-dev.goshops.ai'
                : 'https://discover.gopersonal.ai';
                
            $url = $baseUrl . '/item/search?adapter=magento';
            $urlParams = [];

            // Add search term if exists
            if (isset($queryParams['q'])) {
                $urlParams['query'] = $queryParams['q'];
                unset($queryParams['q']);
            }

            // Handle filters
            if (!empty($queryParams)) {
                $jsonFilter = [];
                
                // Try to get stored buckets if search ID exists
                $storedBuckets = null;
                if ($gsSearchId) {
                    $storedBuckets = json_decode(
                        $this->cache->load('gp_buckets_' . $gsSearchId), 
                        true
                    );
                }

                foreach ($queryParams as $code => $value) {
                    if (!empty($value)) {
                        // Add filter
                        $jsonFilter[$code] = [[
                            'value' => $value,
                            'label' => $value
                        ]];

                        // Check stored buckets for limit
                        if ($storedBuckets) {
                            $bucketKey = $code . self::BUCKET_SUFFIX;
                            if (isset($storedBuckets[$bucketKey])) {
                                foreach ($storedBuckets[$bucketKey]['values'] as $bucketValue) {
                                    if ($bucketValue['value'] == $value) {
                                        $urlParams['limit'] = $bucketValue['metrics']['count'];
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }

                if (!empty($jsonFilter)) {
                    $urlParams['jsonFilter'] = json_encode($jsonFilter);
                }
            }

            // Add gsSearchId if exists
            if ($gsSearchId) {
                $urlParams['_gsSearchId'] = $gsSearchId;
            }

            // Build final URL and make request
            $finalUrl = $url . '&' . http_build_query($urlParams);
            $this->logger->debug("Making request to:", ['url' => $finalUrl]);

            $this->httpClient->addHeader("Authorization", "Bearer " . $token);
            $this->httpClient->addHeader("Content-Type", "application/json");
            $this->httpClient->get($finalUrl);
            $response = $this->httpClient->getBody();

            $result = json_decode($response, true);
            if (!is_array($result)) {
                $this->logger->error("Invalid response format:", ['response' => $response]);
                return [];
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->error("Error getting product IDs: " . $e->getMessage());
            $this->logger->error($e->getTraceAsString());
            return [];
        }
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
                            'label' => $option['label']
                        ];
                    }
                }
            }

            $attributes[$code] = [
                'code' => $code,
                'frontend_label' => $attribute->getFrontendLabel(),
                'backend_type' => $attribute->getBackendType(),
                'frontend_input' => $attribute->getFrontendInput(),
                'options' => $options
            ];
        }
        
        return $attributes;
    }

    protected function getQueryParams(RequestInterface $request): array
    {
        // Read all query parameters from the request
        $queryParams = $this->httpRequest->getParams();
        
        $this->logger->debug("Raw query params:", $queryParams);

        // Get the search term from the request
        if (isset($queryParams['q'])) {
            // Initialize filters array
            $filters = $queryParams;
            unset($filters['q']); // Remove 'q' parameter since we handle it separately
            
            // Log the parameters we'll send
            $this->logger->debug("Search parameters:", [
                'query' => $queryParams['q'],
                'filters' => $filters
            ]);
            
            return $queryParams;
        }

        return [];
    }

    public function aroundSearch(
        SearchEngine $subject,
        callable $proceed,
        RequestInterface $request
    ) {
        $this->logger->debug("SearchEnginePlugin aroundSearch() called");
        
        if (!$this->httpRequest->getParam('gpSearchOverride')) {
            return $proceed($request);
        }

        $this->logger->debug("SearchEnginePlugin: USING CUSTOM SEARCH ENGINE");
        
        try {
            // Test direct product load first
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $productRepository = $objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);

            // $productIds = [1604, 1748, 682];
            $queryParams = $this->getQueryParams($request);
            $this->logger->debug("Query parameters:", $queryParams);

            // Get product IDs
            $productIds = $this->getProductIds($queryParams);
            
            // Debug the product IDs we're looking for
            $this->logger->debug("Searching for product IDs:", $productIds);
            
            // Get filterable attributes
            $filterableAttributes = $this->getFilterableAttributes();
            $this->logger->debug("Loaded filterable attributes:", array_keys($filterableAttributes));

            $this->logger->debug("Filterable attributes:", array_map(function($attr) {
                return [
                    'code' => $attr['code'],
                    'frontend_label' => $attr['frontend_label'],
                    'frontend_input' => $attr['frontend_input'],
                ];
            }, $filterableAttributes));
            

            $collection = $this->productCollectionFactory->create();

            // First add all attributes normally
            $collection->addAttributeToSelect('*');

            // Then add ID filter
            $collection->addIdFilter($productIds);

            // Adding store filter is important for attribute values
            $collection->addStoreFilter();

            // Add website filter to get proper visibility
            $collection->addWebsiteFilter();

            // Now force join for filterable attributes
            foreach ($filterableAttributes as $code => $attribute) {
                $collection->joinAttribute(
                    $code,
                    'catalog_product/' . $code,
                    'entity_id',
                    null,
                    'left'
                );
            }

            // Debug collection before ID filter
            $this->logger->debug("Collection SQL before ID filter:", [
                'sql' => $collection->getSelect()->__toString()
            ]);
            
            $collection->addIdFilter($productIds);

            // Debug collection after ID filter
            $this->logger->debug("Collection SQL after ID filter:", [
                'sql' => $collection->getSelect()->__toString()
            ]);

            // Debug if collection has products
            $this->logger->debug("Collection size: " . $collection->getSize());

            $products = [];
            foreach ($collection as $product) {

                $allData = $product->getData();
                $this->logger->debug("Raw product data from collection:", [
                    'product_id' => $product->getId(),
                    'all_data' => $allData,  // This will show ALL attributes including color
                ]);
                
                $categoryIds = $product->getCategoryIds();
                
                $this->logger->debug("Found product in collection:", [
                    'id' => $product->getId(),
                    'name' => $product->getName(),
                    'sku' => $product->getSku(),
                    'status' => $product->getStatus(),
                    'visibility' => $product->getVisibility(),
                    'categories' => $categoryIds
                ]);

                $productData = [
                    'entity_id' => $product->getId(),
                    'name' => $product->getName(),
                    'price' => (float)$product->getPrice(),
                    'sku' => $product->getSku(),
                    'category_ids' => array_map('intval', $categoryIds),
                ];

                foreach ($filterableAttributes as $code => $attribute) {
                    $value = $product->getData($code);
                    if ($value !== null && !isset($productData[$code])) {
                        $productData[$code] = $value;
                    }
                }

                $products[] = $productData;
            }

            // Now log the products array after it's populated
            $this->logger->debug("Final products array:", $products);

            // Create documents
            $documents = [];
            foreach ($products as $product) {
                $this->logger->debug("Creating document for product:", [
                    'id' => $product['entity_id'],
                    'categories' => $product['category_ids']
                ]);

                $attributes = [
                    'entity_id' => new Value($product['entity_id'], 'entity_id'),
                    'name' => new Value($product['name'], 'name'),
                    'price' => new Value($product['price'], 'price'),
                    'sku' => new Value($product['sku'], 'sku'),
                    'status' => new Value(1, 'status'),
                    'visibility' => new Value(4, 'visibility'),
                    'store_id' => new Value(1, 'store_id'),
                    'category_ids' => new Value(implode(',', $product['category_ids']), 'category_ids')
                ];

                foreach ($filterableAttributes as $code => $attribute) {
                    if (isset($product[$code])) {
                        $value = is_array($product[$code]) ? implode(',', $product[$code]) : $product[$code];
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

            $buckets['price_bucket'] = new \Magento\Framework\Search\Response\Bucket(
                'price_bucket',
                [
                    new Value('90_100', [
                        'from' => 90,
                        'to' => 100,
                        'count' => 1,
                        'value' => '90_100'
                    ], 'price_bucket'),
                    new Value('140_150', [
                        'from' => 140,
                        'to' => 150,
                        'count' => 1,
                        'value' => '140_150'
                    ], 'price_bucket')
                ]
            );

            // Category bucket with logging
            $categoryValues = [];
            $categoryCounts = $this->getValueCounts($products, 'category_ids', true);
            $this->logger->debug("Category counts:", $categoryCounts);

            foreach ($categoryCounts as $value => $count) {
                $valueMetrics = [
                    'value' => $value,
                    'count' => $count
                ];
                
                $categoryValues[] = new Value(
                    (string)$value, 
                    $valueMetrics,
                    'category_bucket'
                );
            }

            $buckets['category_bucket'] = new \Magento\Framework\Search\Response\Bucket(
                'category_bucket',
                $categoryValues
            );

            foreach ($filterableAttributes as $code => $attribute) {
                if ($code === 'price') {
                    continue;
                }
            
                $counts = $this->getValueCounts($products, $code, $attribute['frontend_input'] === 'multiselect');
                $values = [];
                if (!empty($counts)) {
                    foreach ($counts as $value => $count) {
                        $optionLabel = isset($attribute['options'][$value]) ? 
                            $attribute['options'][$value]['label'] : 
                            $value;
            
                        $values[] = new Value((string)$value, [
                            'value' => $value,
                            'label' => $optionLabel,
                            'count' => $count
                        ], $code . self::BUCKET_SUFFIX);
                    }
                }
                
                // Create the bucket regardless of counts
                $buckets[$code . self::BUCKET_SUFFIX] = new \Magento\Framework\Search\Response\Bucket(
                    $code . self::BUCKET_SUFFIX,
                    $values
                );
            }

            $aggregations = new Aggregation($buckets);
            $response = new QueryResponse($documents, $aggregations, count($documents));

            if (isset($queryParams['_gsSearchId'])) {
                // Store buckets associated with search ID
                $this->cache->save(
                    json_encode($buckets),
                    'gp_buckets_' . $queryParams['_gsSearchId'],
                    [],
                    3600  // 1 hour cache
                );
            }

            return $response;

        } catch (\Exception $e) {
            $this->logger->error("SearchEnginePlugin Error: " . $e->getMessage());
            $this->logger->error($e->getTraceAsString());
            throw $e;
        }
    }

    protected function getValueCounts(array $products, string $field, bool $isArray = false): array
    {
        $counts = [];
        foreach ($products as $product) {
            if (isset($product[$field])) {
                if ($isArray || is_array($product[$field])) {
                    foreach ((array)$product[$field] as $value) {
                        if (!isset($counts[$value])) {
                            $counts[$value] = 0;
                        }
                        $counts[$value]++;
                    }
                } else {
                    $value = $product[$field];
                    if (!isset($counts[$value])) {
                        $counts[$value] = 0;
                    }
                    $counts[$value]++;
                }
            }
        }
        return $counts;
    }
}