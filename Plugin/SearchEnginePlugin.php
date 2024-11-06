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
            $filterableAttributes = $this->getFilterableAttributes();
            
            $collection = $this->productCollectionFactory->create()
                ->addAttributeToSelect('*')
                ->addIdFilter($productIds);

            $products = [];
            foreach ($collection as $product) {
                $productData = [
                    'entity_id' => $product->getId(),
                    'name' => $product->getName(),
                    'price' => $product->getPrice(),
                    'sku' => $product->getSku(),
                    'category_ids' => $product->getCategoryIds()
                ];
            
                // Add filterable attributes
                foreach ($filterableAttributes as $code => $attribute) {
                    $value = $product->getData($code);
                    if ($value !== null) {
                        $productData[$code] = $value;
                    }
                }
            
                $products[] = $productData;
            }

            $this->logger->debug('Processed products data:', $products);

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
                    'store_id' => new Value(1, 'store_id')
                ];

                // Handle category_ids specially
                if (isset($product['category_ids'])) {
                    $categoryIds = is_array($product['category_ids']) ? 
                        implode(',', $product['category_ids']) : 
                        $product['category_ids'];
                    $attributes['category_ids'] = new Value($categoryIds, 'category_ids');
                }

                // Handle other attributes
                foreach ($filterableAttributes as $code => $attribute) {
                    if (isset($product[$code]) && $code !== 'category_ids') {
                        $value = is_array($product[$code]) ? 
                            implode(',', $product[$code]) : 
                            $product[$code];
                        $attributes[$code] = new Value($value, $code);
                    }
                }

                $document = new Document();
                $document->setId($product['entity_id']);
                $document->setCustomAttributes($attributes);
                $documents[] = $document;
            }

            // Create buckets
            $buckets = [];
            
            // Price bucket
            $buckets['price_bucket'] = new \Magento\Framework\Search\Response\Bucket(
                'price_bucket',
                [
                    new Value('0_50', [
                        'from' => 0,
                        'to' => 50,
                        'count' => count(array_filter($products, function($p) { 
                            return $p['price'] <= 50; 
                        }))
                    ], 'price_bucket')
                ]
            );

            // Category bucket
            $categoryCounts = $this->getValueCounts($products, 'category_ids', true);
            $categoryValues = [];
            foreach ($categoryCounts as $categoryId => $count) {
                $categoryValues[] = new Value((string)$categoryId, [
                    'value' => $categoryId,
                    'count' => $count
                ], 'category_bucket');
            }
            
            $buckets['category_bucket'] = new \Magento\Framework\Search\Response\Bucket(
                'category_bucket',
                $categoryValues
            );

            // Other attribute buckets
            foreach ($filterableAttributes as $code => $attribute) {
                if ($code === 'price' || $code === 'category_ids') {
                    continue;
                }
                
                $counts = $this->getValueCounts($products, $code, true);
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
                
                if (!empty($values)) {
                    $buckets[$code . self::BUCKET_SUFFIX] = new \Magento\Framework\Search\Response\Bucket(
                        $code . self::BUCKET_SUFFIX,
                        $values
                    );
                }
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
                // Handle array values (whether native array or string that needs to be split)
                $values = $product[$field];
                if (!is_array($values) && strpos($values, ',') !== false) {
                    $values = explode(',', $values);
                } else if (!is_array($values)) {
                    $values = [$values];
                }
                
                // Count each value
                foreach ($values as $value) {
                    $value = trim($value);
                    if ($value === '') continue;
                    
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