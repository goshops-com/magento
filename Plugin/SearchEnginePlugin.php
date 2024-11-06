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

class SearchEnginePlugin
{
    protected $logger;
    protected $httpRequest;
    protected $objectManager;

    public function __construct(
        LoggerInterface $logger,
        HttpRequestInterface $httpRequest,
        ObjectManagerInterface $objectManager
    ) {
        $this->logger = $logger;
        $this->httpRequest = $httpRequest;
        $this->objectManager = $objectManager;
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
                    'category_ids' => new Value(implode(',', $product['category_ids']), 'category_ids'),
                    'category' => new Value(implode(',', $product['category_ids']), 'category')
                ]);
                $documents[] = $document;
            }

            // Count products in each category
            $categoryCounts = [];
            foreach ($products as $product) {
                foreach ($product['category_ids'] as $catId) {
                    if (!isset($categoryCounts[$catId])) {
                        $categoryCounts[$catId] = 0;
                    }
                    $categoryCounts[$catId]++;
                }
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
                    array_map(function($catId, $count) {
                        return new Value($catId, [
                            'value' => $catId,
                            'count' => $count
                        ], 'category');
                    }, array_keys($categoryCounts), array_values($categoryCounts))
                ),
                'category_ids' => new \Magento\Framework\Search\Response\Bucket(
                    'category_ids',
                    array_map(function($catId, $count) {
                        return new Value($catId, [
                            'value' => $catId,
                            'count' => $count
                        ], 'category_ids');
                    }, array_keys($categoryCounts), array_values($categoryCounts))
                )
            ];

            $aggregations = new Aggregation($buckets);
            $response = new QueryResponse($documents, $aggregations, count($documents));

            $this->logger->debug('Created response with buckets: ' . print_r([
                'bucket_names' => array_keys($buckets),
                'category_counts' => $categoryCounts
            ], true));

            return $response;

        } catch (\Exception $e) {
            $this->logger->error("SearchEnginePlugin Error: " . $e->getMessage());
            throw $e;
        }
    }
}