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
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Catalog\Model\Layer\Category\FilterableAttributeList;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\Product\Attribute\Repository as AttributeRepository;

class SearchEnginePlugin
{
    const BUCKET_SUFFIX = '_bucket';

    protected $logger;
    protected $httpRequest;
    protected $objectManager;
    protected $attributeCollectionFactory;
    protected $filterableAttributeList;
    protected $storeManager;
    protected $attributeRepository;

    public function __construct(
        LoggerInterface $logger,
        HttpRequestInterface $httpRequest,
        ObjectManagerInterface $objectManager,
        AttributeCollectionFactory $attributeCollectionFactory,
        FilterableAttributeList $filterableAttributeList,
        StoreManagerInterface $storeManager,
        AttributeRepository $attributeRepository
    ) {
        $this->logger = $logger;
        $this->httpRequest = $httpRequest;
        $this->objectManager = $objectManager;
        $this->attributeCollectionFactory = $attributeCollectionFactory;
        $this->filterableAttributeList = $filterableAttributeList;
        $this->storeManager = $storeManager;
        $this->attributeRepository = $attributeRepository;
    }

    /**
     * Get all filterable attributes
     *
     * @return array
     */
    protected function getFilterableAttributes(): array
    {
        $attributes = $this->filterableAttributeList->getList();
        $filterableAttributes = [];
        
        foreach ($attributes as $attribute) {
            $filterableAttributes[$attribute->getAttributeCode()] = [
                'code' => $attribute->getAttributeCode(),
                'frontend_label' => $attribute->getFrontendLabel(),
                'backend_type' => $attribute->getBackendType(),
                'frontend_input' => $attribute->getFrontendInput(),
                'options' => $this->getAttributeOptions($attribute)
            ];
        }
        
        $this->logger->debug('Filterable attributes: ' . print_r($filterableAttributes, true));
        
        return $filterableAttributes;
    }

    /**
     * Get attribute options
     *
     * @param \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attribute
     * @return array
     */
    protected function getAttributeOptions($attribute): array
    {
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
        return $options;
    }

    /**
     * Handle different attribute types for bucket values
     *
     * @param mixed $value
     * @param string $attributeCode
     * @param array $attribute
     * @return mixed
     */
    protected function formatAttributeValue($value, string $attributeCode, array $attribute)
    {
        switch ($attributeCode) {
            case 'price':
                return number_format((float)$value, 2, '_', '');
            
            case 'category_ids':
                return (string)$value;

            default:
                // For select/multiselect attributes, ensure we have string values
                if (isset($attribute['frontend_input']) 
                    && in_array($attribute['frontend_input'], ['select', 'multiselect'])
                ) {
                    return (string)$value;
                }
                return $value;
        }
    }

    /**
     * Create bucket based on attribute type
     *
     * @param string $attributeCode
     * @param array $values
     * @param array $attribute
     * @return \Magento\Framework\Search\Response\Bucket
     */
    protected function createBucket(string $attributeCode, array $values, array $attribute)
    {
        $bucketName = $attributeCode . self::BUCKET_SUFFIX;
        
        switch ($attributeCode) {
            case 'price':
                $bucketValues = [];
                foreach ($values as $value => $count) {
                    $rangeLower = floor((float)$value / 10) * 10;
                    $rangeUpper = $rangeLower + 10;
                    $rangeKey = $rangeLower . '_' . $rangeUpper;
                    
                    if (!isset($bucketValues[$rangeKey])) {
                        $bucketValues[$rangeKey] = [
                            'value' => $rangeKey,
                            'count' => 0,
                            'from' => $rangeLower,
                            'to' => $rangeUpper
                        ];
                    }
                    $bucketValues[$rangeKey]['count'] += $count;
                }
                
                return new \Magento\Framework\Search\Response\Bucket(
                    $bucketName,
                    array_map(function($range) use ($bucketName) {
                        return new Value($range['value'], $range, $bucketName);
                    }, array_values($bucketValues))
                );

            case 'category_ids':
                return new \Magento\Framework\Search\Response\Bucket(
                    $bucketName,
                    array_map(function($value, $count) use ($bucketName) {
                        return new Value((string)$value, [
                            'value' => $value,
                            'count' => $count
                        ], $bucketName);
                    }, array_keys($values), array_values($values))
                );
                
            default:
                return new \Magento\Framework\Search\Response\Bucket(
                    $bucketName,
                    array_map(function($value, $count) use ($bucketName, $attribute) {
                        $metrics = [
                            'value' => $value,
                            'count' => $count
                        ];
                        
                        // Add label for select/multiselect attributes
                        if (isset($attribute['frontend_input']) 
                            && in_array($attribute['frontend_input'], ['select', 'multiselect'])
                            && isset($attribute['options'][$value]['label'])
                        ) {
                            $metrics['label'] = $attribute['options'][$value]['label'];
                        }
                        
                        return new Value((string)$value, $metrics, $bucketName);
                    }, array_keys($values), array_values($values))
                );
        }
    }

    /**
     * Get attribute value counts from products with type handling
     *
     * @param array $products
     * @param string $attributeCode
     * @param array $attribute
     * @return array
     */
    protected function getAttributeCounts(array $products, string $attributeCode, array $attribute): array 
    {
        $counts = [];
        foreach ($products as $product) {
            if (isset($product[$attributeCode])) {
                if (is_array($product[$attributeCode])) {
                    // Handle multiselect attributes (like category_ids)
                    foreach ($product[$attributeCode] as $value) {
                        if (!isset($counts[$value])) {
                            $counts[$value] = 0;
                        }
                        $counts[$value]++;
                    }
                } else {
                    $value = $product[$attributeCode];
                    if (!isset($counts[$value])) {
                        $counts[$value] = 0;
                    }
                    $counts[$value]++;
                }
            }
        }
        return $counts;
    }

    public function aroundSearch(
        SearchEngine $subject,
        callable $proceed,
        RequestInterface $request
    ) {
        $this->logger->debug("SearchEnginePlugin aroundSearch() called");
        
        if (!$this->httpRequest->getParam('gpSearchOverride')) {
            $result = $proceed($request);
            if ($result instanceof QueryResponse) {
                // Log the default response structure
                $buckets = $result->getAggregations()->getBuckets();
                foreach ($buckets as $key => $bucket) {
                    $this->logger->debug("Default bucket {$key}: " . print_r([
                        'name' => $bucket->getName(),
                        'values' => array_map(function($value) {
                            return [
                                'value' => $value->getValue(),
                                'metrics' => $value->getMetrics(),
                                'field' => $value->getField()
                            ];
                        }, $bucket->getValues())
                    ], true));
                }
            }
            return $result;
        }

        $this->logger->debug("SearchEnginePlugin: USING CUSTOM SEARCH ENGINE");
        
        try {
            // Products with multiple categories
            $products = [
                [
                    'entity_id' => '1',
                    'name' => 'Test Product 1',
                    'price' => 99.99,
                    'sku' => 'TEST-1',
                    'category_ids' => [3, 9, 20]
                ],
                [
                    'entity_id' => '2',
                    'name' => 'Test Product 2',
                    'price' => 149.99,
                    'sku' => 'TEST-2',
                    'category_ids' => [3, 11, 37]
                ]
            ];

            // Create documents
            $documents = [];
            foreach ($products as $product) {
                $document = new Document();
                $document->setId($product['entity_id']);
                $document->setCustomAttributes([
                    'entity_id' => new Value($product['entity_id'], 'entity_id'),
                    'name' => new Value($product['name'], 'name'),
                    'price' => new Value($product['price'], 'price'),
                    'sku' => new Value($product['sku'], 'sku'),
                    'status' => new Value(1, 'status'),
                    'visibility' => new Value(4, 'visibility'),
                    'store_id' => new Value(1, 'store_id'),
                    'category_ids' => new Value(implode(',', $product['category_ids']), 'category_ids')
                ]);
                $documents[] = $document;
            }

            // Create category values
            $categoryValues = [];
            $categoryCounts = [];
            foreach ($products as $product) {
                foreach ($product['category_ids'] as $catId) {
                    if (!isset($categoryCounts[$catId])) {
                        $categoryCounts[$catId] = 0;
                    }
                    $categoryCounts[$catId]++;
                }
            }

            foreach ($categoryCounts as $catId => $count) {
                $categoryValues[] = new Value((string)$catId, [
                    'value' => $catId,
                    'count' => $count,
                    'title' => "Category {$catId}"  // Added title
                ], 'category');
            }

            // Create buckets
            $buckets = [
                'price_bucket' => new \Magento\Framework\Search\Response\Bucket(
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
                ),
                'category' => new \Magento\Framework\Search\Response\Bucket(
                    'category',
                    $categoryValues
                )
            ];

            // Log the bucket structure we're creating
            foreach ($buckets as $key => $bucket) {
                $this->logger->debug("Custom bucket {$key}: " . print_r([
                    'name' => $bucket->getName(),
                    'values' => array_map(function($value) {
                        return [
                            'value' => $value->getValue(),
                            'metrics' => $value->getMetrics(),
                            'field' => $value->getField()
                        ];
                    }, $bucket->getValues())
                ], true));
            }

            $aggregations = new Aggregation($buckets);
            $response = new QueryResponse($documents, $aggregations, count($documents));

            return $response;

        } catch (\Exception $e) {
            $this->logger->error("SearchEnginePlugin Error: " . $e->getMessage());
            throw $e;
        }
    }
}