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

            $productIds = [1, 2];

            
            // Get filterable attributes
            $filterableAttributes = $this->getFilterableAttributes();
            
            // Products with multiple categories and attributes
            $products = [
                [
                    'entity_id' => '1',
                    'name' => 'Test Product 1',
                    'price' => 99.99,
                    'sku' => 'TEST-1',
                    'category_ids' => [3, 9, 20],
                    'size' => '166',      // XS
                ],
                [
                    'entity_id' => '2',
                    'name' => 'Test Product 2',
                    'price' => 149.99,
                    'sku' => 'TEST-2',
                    'category_ids' => [3, 11, 37],

                    'size' => '167',      // S
                ]
            ];

            $this->logger->debug("Original products data for categories:", $products);

            $collection = $this->productCollectionFactory->create()
                ->addAttributeToSelect('*')
                ->addIdFilter($productIds);

            $products2 = [];

            foreach ($collection as $product) {
                $products2[] = [
                    'entity_id' => $product->getId(),
                    'name' => $product->getName(),
                    'price' => $product->getPrice(),
                    'sku' => $product->getSku(),
                    'category_ids' => $product->getCategoryIds(),
                    'size' => $product->getData('size'),
                ];
            }

            // Log the fetched products data
            $this->logger->debug('Fetched products data:', $products2);

            // Create documents
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
            $this->logger->debug("Category counts from getValueCounts:", $categoryCounts);
            
            foreach ($categoryCounts as $value => $count) {
                $categoryValues[] = new Value((string)$value, [
                    'value' => $value,
                    'count' => $count
                ], 'category_bucket');
            }
            $this->logger->debug("Created category values:", $categoryValues);
            
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

            return $response;

        } catch (\Exception $e) {
            $this->logger->error("SearchEnginePlugin Error: " . $e->getMessage());
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