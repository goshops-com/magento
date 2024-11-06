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
            // Get default response first to preserve bucket structure
            $defaultResponse = $proceed($request);
            $this->logger->debug('Default buckets: ' . print_r(
                array_keys($defaultResponse->getAggregations()->getBuckets()),
                true
            ));

            // Products with multiple categories
            $products = [
                [
                    'entity_id' => '1',
                    'name' => 'Test Product 1',
                    'price' => 99.99,
                    'sku' => 'TEST-1',
                    'category_ids' => [3, 9, 20],
                    // Add sample values for other filterable attributes
                    'color' => 'red',
                    'size' => 'L',
                    'manufacturer' => 'brand1'
                ],
                [
                    'entity_id' => '2',
                    'name' => 'Test Product 2',
                    'price' => 149.99,
                    'sku' => 'TEST-2',
                    'category_ids' => [3, 11, 37],
                    // Add sample values for other filterable attributes
                    'color' => 'blue',
                    'size' => 'M',
                    'manufacturer' => 'brand2'
                ]
            ];

            // Get filterable attributes
            $filterableAttributes = $this->getFilterableAttributes();

            // Create documents with all filterable attributes
            $documents = [];
            foreach ($products as $product) {
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

                // Add all filterable attributes to document
                foreach ($filterableAttributes as $code => $attribute) {
                    if (isset($product[$code])) {
                        $attributes[$code] = new Value($product[$code], $code);
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

            // Category bucket
            $categoryValues = $this->getCategoryCounts($products);
            $buckets['category_bucket'] = new \Magento\Framework\Search\Response\Bucket(
                'category_bucket',
                array_map(function($catId, $count) {
                    return new Value((string)$catId, [
                        'value' => $catId,
                        'count' => $count
                    ], 'category_bucket');
                }, array_keys($categoryValues), array_values($categoryValues))
            );

            // Add buckets for each filterable attribute
            foreach ($filterableAttributes as $code => $attribute) {
                $bucketName = $code . self::BUCKET_SUFFIX;
                $attributeValues = $this->getAttributeCounts($products, $code);
                
                if (!empty($attributeValues)) {
                    $bucketValues = [];
                    foreach ($attributeValues as $value => $count) {
                        $bucketValues[] = new Value((string)$value, [
                            'value' => $value,
                            'count' => $count
                        ], $bucketName);
                    }
                    
                    $buckets[$bucketName] = new \Magento\Framework\Search\Response\Bucket(
                        $bucketName,
                        $bucketValues
                    );
                }
            }

            $this->logger->debug('Created buckets with names: ' . print_r(array_keys($buckets), true));

            $aggregations = new Aggregation($buckets);
            $response = new QueryResponse($documents, $aggregations, count($documents));

            return $response;

        } catch (\Exception $e) {
            $this->logger->error("SearchEnginePlugin Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get category counts from products
     *
     * @param array $products
     * @return array
     */
    protected function getCategoryCounts(array $products): array
    {
        $counts = [];
        foreach ($products as $product) {
            foreach ($product['category_ids'] as $catId) {
                if (!isset($counts[$catId])) {
                    $counts[$catId] = 0;
                }
                $counts[$catId]++;
            }
        }
        return $counts;
    }

    /**
     * Get attribute value counts from products
     *
     * @param array $products
     * @param string $attributeCode
     * @return array
     */
    protected function getAttributeCounts(array $products, string $attributeCode): array
    {
        $counts = [];
        foreach ($products as $product) {
            if (isset($product[$attributeCode])) {
                $value = $product[$attributeCode];
                if (!isset($counts[$value])) {
                    $counts[$value] = 0;
                }
                $counts[$value]++;
            }
        }
        return $counts;
    }
}