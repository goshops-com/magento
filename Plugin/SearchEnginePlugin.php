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
use Magento\Catalog\Model\ProductRepository;

class SearchEnginePlugin
{
    const BUCKET_SUFFIX = '_bucket';

    protected $logger;
    protected $httpRequest;
    protected $objectManager;
    protected $productRepository;

    public function __construct(
        LoggerInterface $logger,
        HttpRequestInterface $httpRequest,
        ObjectManagerInterface $objectManager,
        FilterableAttributeList $filterableAttributeList,
        ProductRepository $productRepository
    ) {
        $this->logger = $logger;
        $this->httpRequest = $httpRequest;
        $this->objectManager = $objectManager;
        $this->filterableAttributeList = $filterableAttributeList;
        $this->productRepository = $productRepository;
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

        $this->logger->debug('Filterable attributes found:', $attributes);
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
            // Get filterable attributes
            $filterableAttributes = $this->getFilterableAttributes();
            
            // Load products
            $productIds = [1, 2];
            $products = [];
            
            foreach ($productIds as $productId) {
                try {
                    $product = $this->productRepository->getById($productId);
                    
                    $this->logger->debug("Product {$productId} raw data:", [
                        'product_data' => $product->getData(),
                        'color' => $product->getData('color'),
                        'size' => $product->getData('size')
                    ]);
                    
                    $productData = [
                        'entity_id' => $product->getId(),
                        'name' => $product->getName(),
                        'price' => $product->getPrice(),
                        'sku' => $product->getSku(),
                        'category_ids' => $product->getCategoryIds(),
                        'color' => $product->getData('color'),
                        'size' => $product->getData('size')
                    ];
                    
                    $this->logger->debug("Product {$productId} processed data:", $productData);
                    $products[] = $productData;
                    
                } catch (\Exception $e) {
                    $this->logger->error("Error loading product {$productId}: " . $e->getMessage());
                }
            }

            $this->logger->debug("Final products array:", $products);
            
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
                        $this->logger->debug("Added attribute {$code} with value {$value} to document");
                    }
                }

                $document = new Document();
                $document->setId($product['entity_id']);
                $document->setCustomAttributes($attributes);
                $documents[] = $document;
            }

            $this->logger->debug("Creating buckets");
            
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

            $categoryValues = [];
            $categoryCounts = $this->getValueCounts($products, 'category_ids', true);
            foreach ($categoryCounts as $value => $count) {
                $categoryValues[] = new Value((string)$value, [
                    'value' => $value,
                    'count' => $count
                ], 'category_bucket');
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
                $this->logger->debug("Counts for attribute {$code}:", $counts);
                
                if (!empty($counts)) {
                    $values = [];
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
                    
                    $buckets[$code . self::BUCKET_SUFFIX] = new \Magento\Framework\Search\Response\Bucket(
                        $code . self::BUCKET_SUFFIX,
                        $values
                    );
                    $this->logger->debug("Created bucket for {$code}");
                }
            }

            $this->logger->debug('Created buckets:', array_keys($buckets));

            $aggregations = new Aggregation($buckets);
            $response = new QueryResponse($documents, $aggregations, count($documents));

            return $response;

        } catch (\Exception $e) {
            $this->logger->error("SearchEnginePlugin Error: " . $e->getMessage());
            $this->logger->error("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    protected function getValueCounts(array $products, string $field, bool $isArray = false): array
    {
        $this->logger->debug("Getting value counts for {$field}, isArray: " . ($isArray ? 'true' : 'false'));
        $this->logger->debug("Products for counting:", $products);
        
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
        
        $this->logger->debug("Counts result for {$field}:", $counts);
        return $counts;
    }
}