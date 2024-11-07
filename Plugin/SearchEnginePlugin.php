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

class SearchEnginePlugin
{
    const BUCKET_SUFFIX = '_bucket';

    protected $logger;
    protected $httpRequest;
    protected $objectManager;
    protected $productCollectionFactory;

    public function __construct(
        LoggerInterface $logger,
        HttpRequestInterface $httpRequest,
        ObjectManagerInterface $objectManager,
        FilterableAttributeList $filterableAttributeList, 
        ProductCollectionFactory $productCollectionFactory
    ) {
        $this->logger = $logger;
        $this->httpRequest = $httpRequest;
        $this->objectManager = $objectManager;
        $this->filterableAttributeList = $filterableAttributeList;
        $this->productCollectionFactory = $productCollectionFactory;
    }

    public function aroundSearch(
        SearchEngine $subject,
        callable $proceed,
        RequestInterface $request
    ) {
        $this->logger->debug("SearchEnginePlugin aroundSearch() called");
        // $this->logger->debug("Request query details:", [
        //     'query' => $request->getQuery()->__toString(),
        //     'dimensions' => array_keys($request->getDimensions())
        // ]);
        
        if (!$this->httpRequest->getParam('gpSearchOverride')) {
            return $proceed($request);
        }

        $this->logger->debug("SearchEnginePlugin: USING CUSTOM SEARCH ENGINE");
        
        try {
            $productIds = [1, 2];
            
            // Get filterable attributes
            $filterableAttributes = $this->getFilterableAttributes();
            $this->logger->debug("Filterable attributes:", array_keys($filterableAttributes));
            
            $collection = $this->productCollectionFactory->create()
                ->addAttributeToSelect('*')
                ->addIdFilter($productIds);

            $products = [];
            foreach ($collection as $product) {
                $categoryIds = $product->getCategoryIds();
                $this->logger->debug("Product categories for ID " . $product->getId() . ":", [
                    'raw_categories' => $product->getCategoryIds(),
                    'processed_categories' => array_map('intval', $categoryIds)
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

            $this->logger->debug('Processed products data:', $products);

            // Create documents
            $documents = [];
            foreach ($products as $product) {
                $this->logger->debug("Processing document for product ID: " . $product['entity_id'], [
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
                ];

                // Special handling for category_ids
                if (isset($product['category_ids']) && is_array($product['category_ids'])) {
                    $categoryValue = implode(',', $product['category_ids']);
                    $this->logger->debug("Setting category_ids for document:", [
                        'product_id' => $product['entity_id'],
                        'categories' => $product['category_ids'],
                        'category_string' => $categoryValue
                    ]);
                    $attributes['category_ids'] = new Value($categoryValue, 'category_ids');
                }

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

            // Price bucket
            $buckets['price_bucket'] = new \Magento\Framework\Search\Response\Bucket(
                'price_bucket',
                [
                    new Value('30_40', [
                        'from' => 30,
                        'to' => 40,
                        'count' => 2,
                        'value' => '30_40'
                    ], 'price_bucket')
                ]
            );

            // Category bucket with enhanced logging
            $categoryValues = [];
            $categoryCounts = $this->getValueCounts($products, 'category_ids', true);
            $this->logger->debug("Raw category counts:", $categoryCounts);

            foreach ($categoryCounts as $value => $count) {
                $valueMetrics = [
                    'value' => $value,
                    'label' => 'Category ' . $value,  // Added label
                    'count' => $count
                ];
                
                $this->logger->debug("Creating category bucket value:", [
                    'category_id' => $value,
                    'metrics' => $valueMetrics
                ]);
                
                $categoryValues[] = new Value(
                    (string)$value, 
                    $valueMetrics,
                    'category_bucket'
                );
            }

            $this->logger->debug("Final category values array:", array_map(function($value) {
                return [
                    'value' => $value->getValue(),
                    'metrics' => $value->getMetrics(),
                    'field' => $value->getField()
                ];
            }, $categoryValues));

            $buckets['category_bucket'] = new \Magento\Framework\Search\Response\Bucket(
                'category_bucket',
                $categoryValues
            );

            // Process other filterable attributes
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
                
                $buckets[$code . self::BUCKET_SUFFIX] = new \Magento\Framework\Search\Response\Bucket(
                    $code . self::BUCKET_SUFFIX,
                    $values
                );
            }

            $aggregations = new Aggregation($buckets);
            $response = new QueryResponse($documents, $aggregations, count($documents));

            $this->logger->debug("Final response structure:", [
                'total_count' => $response->count(),
                'buckets' => array_map(function($bucket) {
                    return [
                        'name' => $bucket->getName(),
                        'values_count' => count($bucket->getValues()),
                        'sample_value' => !empty($bucket->getValues()) ? [
                            'value' => $bucket->getValues()[0]->getValue(),
                            'metrics' => $bucket->getValues()[0]->getMetrics()
                        ] : null
                    ];
                }, $response->getAggregations()->getBuckets())
            ]);

            return $response;

        } catch (\Exception $e) {
            $this->logger->error("SearchEnginePlugin Error: " . $e->getMessage());
            $this->logger->error($e->getTraceAsString());
            throw $e;
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