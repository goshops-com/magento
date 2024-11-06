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
        
        $this->logger->debug('Filterable attributes loaded: ' . print_r($attributes, true));
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
            // Get filterable attributes first
            $filterableAttributes = $this->getFilterableAttributes();
            
            $this->logger->debug("Filterable attributes:", $filterableAttributes);
            
            // Define product IDs we want to use
            $productIds = [1, 2];
            $products = [];
            
            foreach ($productIds as $productId) {
                try {
                    $product = $this->productRepository->getById($productId);
                    
                    // Start with basic product data
                    $productData = [
                        'entity_id' => $product->getId(),
                        'name' => $product->getName(),
                        'price' => $product->getPrice(),
                        'sku' => $product->getSku(),
                        'category_ids' => $product->getCategoryIds(),
                    ];
                    
                    // Add all filterable attributes
                    foreach ($filterableAttributes as $code => $attribute) {
                        $value = $product->getData($code);
                        if ($value !== null) {
                            $productData[$code] = $value;
                            $this->logger->debug("Added attribute {$code} with value {$value} for product {$productId}");
                        }
                    }
                    
                    $products[] = $productData;
                    
                } catch (\Exception $e) {
                    $this->logger->error("Error loading product {$productId}: " . $e->getMessage());
                }
            }
            
            $this->logger->debug("Loaded products data:", $products);

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

                // Add all filterable attributes to documents
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

            // Create all required buckets
            $buckets = [];

            // Create bucket for EACH filterable attribute
            foreach ($filterableAttributes as $code => $attribute) {
                $counts = $this->getValueCounts($products, $code, $attribute['frontend_input'] === 'multiselect');
                $this->logger->debug("Creating bucket for {$code}, counts:", $counts);
                
                if (!empty($counts)) {
                    $values = [];
                    foreach ($counts as $value => $count) {
                        // Special handling for price bucket
                        if ($code === 'price') {
                            $rangeStart = floor($value / 10) * 10;
                            $rangeEnd = $rangeStart + 10;
                            $values[] = new Value("{$rangeStart}_{$rangeEnd}", [
                                'from' => $rangeStart,
                                'to' => $rangeEnd,
                                'count' => $count,
                                'value' => "{$rangeStart}_{$rangeEnd}"
                            ], 'price_bucket');
                        } else {
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
                    
                    if (!empty($values)) {
                        $bucketName = $code . self::BUCKET_SUFFIX;
                        $buckets[$bucketName] = new \Magento\Framework\Search\Response\Bucket(
                            $bucketName,
                            $values
                        );
                        $this->logger->debug("Created bucket: {$bucketName}");
                    }
                }
            }

            $this->logger->debug("Created buckets:", array_keys($buckets));

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
        $this->logger->debug("Counting values for field {$field}, isArray: " . ($isArray ? 'true' : 'false'));
        
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
        
        $this->logger->debug("Counts result for {$field}: " . print_r($counts, true));
        return $counts;
    }
}