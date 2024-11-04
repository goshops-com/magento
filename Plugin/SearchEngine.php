<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Api\Search\Document as SearchDocument;
use Magento\Framework\Search\Response\QueryResponse;
use Magento\Framework\Search\Response\Aggregation;
use Magento\Framework\Search\Response\Aggregation\Value;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\RequestInterface as HttpRequestInterface;
use Magento\Search\Model\SearchEngine as MagentoSearchEngine;
use Magento\Framework\ObjectManagerInterface;
use Magento\Search\Model\AdapterFactory;
use Magento\Framework\Search\Dynamic\IntervalFactory;

class SearchEngine extends MagentoSearchEngine
{
    // ... (keep existing properties and constructor)

    public function search(RequestInterface $request)
    {
        if (!$this->httpRequest->getParam('gpSearchOverride')) {
            var_dump("USING DEFAULT MAGENTO SEARCH");
            return parent::search($request);
        }

        var_dump("USING CUSTOM SEARCH ENGINE");
        
        try {
            // Your existing product data
            $products = [
                [
                    'entity_id' => '1',
                    'name' => 'Test Product 1',
                    'price' => 99.99,
                    'sku' => 'TEST-1',
                    'category_ids' => [2, 3]
                ],
                [
                    'entity_id' => '2',
                    'name' => 'Test Product 2',
                    'price' => 149.99,
                    'sku' => 'TEST-2',
                    'category_ids' => [2, 4]
                ]
            ];

            $documents = [];
            foreach ($products as $product) {
                $documentData = [
                    'entity_id' => $product['entity_id'],
                    'id' => $product['entity_id'],
                    '_id' => $product['entity_id'],
                    '_score' => 1,
                    'score' => 1,
                    'visibility' => 4,
                    'status' => 1,
                    'type_id' => 'simple',
                    'store_id' => 1,
                    'website_ids' => [1],
                    '_type' => 'product',
                    '_index' => 'catalog_product',
                    'category_ids' => $product['category_ids'],
                    '_source' => [
                        'entity_id' => $product['entity_id'],
                        'name' => $product['name'],
                        'sku' => $product['sku'],
                        'price' => $product['price'],
                        'status' => 1,
                        'visibility' => 4,
                        'type_id' => 'simple',
                        'store_id' => 1,
                        'category_ids' => $product['category_ids']
                    ]
                ];

                $documents[] = new SearchDocument(
                    $documentData,
                    [
                        'entity_id' => new Value($product['entity_id'], 'entity_id'),
                        'name' => new Value($product['name'], 'name'),
                        'price' => new Value($product['price'], 'price'),
                        'sku' => new Value($product['sku'], 'sku'),
                        'status' => new Value(1, 'status'),
                        'visibility' => new Value(4, 'visibility'),
                        'store_id' => new Value(1, 'store_id'),
                        'score' => new Value(1, 'score'),
                        'category_ids' => new Value($product['category_ids'], 'category_ids')
                    ]
                );
            }

            // Create aggregations with all required buckets
            $buckets = [
                'category' => [
                    'name' => 'category',
                    'values' => [
                        ['value' => 2, 'count' => 2],
                        ['value' => 3, 'count' => 1],
                        ['value' => 4, 'count' => 1]
                    ]
                ],
                'price_bucket' => [
                    'name' => 'price_bucket',
                    'values' => [
                        ['from' => 0, 'to' => 100, 'count' => 1],
                        ['from' => 100, 'to' => 200, 'count' => 1]
                    ]
                ]
            ];

            $aggregations = new Aggregation(
                [
                    'category' => new Value(2, 'category'),
                    'price_bucket' => new Value(99.99, 'price')
                ],
                $buckets
            );

            // Store product IDs in request for later use
            $productIds = array_column($products, 'entity_id');
            $this->httpRequest->setParam('custom_product_ids', $productIds);

            return new QueryResponse($documents, $aggregations, count($documents));

        } catch (\Exception $e) {
            var_dump("Error in search engine:", $e->getMessage());
            throw $e;
        }
    }
}