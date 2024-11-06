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
            $this->logger->debug("SearchEnginePlugin: USING DEFAULT MAGENTO SEARCH");
            return $proceed($request);
        }

        $this->logger->debug("SearchEnginePlugin: USING CUSTOM SEARCH ENGINE");
        
        try {
            // Hardcoded test products
            $products = [
                [
                    'entity_id' => '1',
                    'name' => 'Test Product 1',
                    'price' => 99.99,
                    'sku' => 'TEST-1'
                ],
                [
                    'entity_id' => '2',
                    'name' => 'Test Product 2',
                    'price' => 149.99,
                    'sku' => 'TEST-2'
                ]
            ];

            $documents = [];
            
            foreach ($products as $product) {
                // Create a document fields array
                $fields = [
                    'entity_id' => new Value($product['entity_id'], 'entity_id'),
                    'name' => new Value($product['name'], 'name'),
                    'price' => new Value($product['price'], 'price'),
                    'sku' => new Value($product['sku'], 'sku'),
                    'status' => new Value(1, 'status'),
                    'visibility' => new Value(4, 'visibility'),
                    'store_id' => new Value(1, 'store_id'),
                    'score' => new Value(1, 'score')
                ];

                // Create the document
                $document = new Document();
                $document->setId($product['entity_id']);
                $document->setCustomAttributes($fields);

                $documents[] = $document;
            }

            // Create proper bucket values with field names
            $priceBucketValues = [
                new Value('0_10', [
                    'from' => 0,
                    'to' => 10,
                    'count' => 0,
                    'value' => '0_10'
                ], 'price_bucket'),
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
            ];

            $categoryBucketValues = [
                new Value(3, [
                    'value' => 3,
                    'count' => 2
                ], 'category_bucket')
            ];

            // Create the aggregation buckets
            $buckets = [
                'price_bucket' => new \Magento\Framework\Search\Response\Bucket(
                    'price_bucket',
                    $priceBucketValues
                ),
                'category_bucket' => new \Magento\Framework\Search\Response\Bucket(
                    'category_bucket',
                    $categoryBucketValues
                )
            ];

            // Create the aggregation object with the buckets
            $aggregations = new Aggregation($buckets);

            return new QueryResponse($documents, $aggregations, count($documents));

        } catch (\Exception $e) {
            $this->logger->error("SearchEnginePlugin Error: " . $e->getMessage());
            throw $e;
        }
    }
}