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
            $result = $proceed($request);
            // Log the original response structure
            if ($result instanceof QueryResponse) {
                $this->logger->debug('Original search response structure: ' . print_r([
                    'buckets' => array_keys($result->getAggregations()->getBuckets()),
                ], true));
            }
            return $result;
        }

        $this->logger->debug("SearchEnginePlugin: USING CUSTOM SEARCH ENGINE");
        
        try {
            // Log detailed request information
            $this->logger->debug('Request details: ' . print_r([
                'aggregations' => $request->getAggregation(),
                'name' => $request->getName(),
                'dimension_data' => array_map(function($dimension) {
                    return $dimension->getValue();
                }, $request->getDimensions())
            ], true));

            // Get original response to see structure
            $originalResponse = $proceed($request);
            if ($originalResponse instanceof QueryResponse) {
                $this->logger->debug('Original bucket structure: ' . print_r([
                    'bucket_names' => array_keys($originalResponse->getAggregations()->getBuckets())
                ], true));
            }

            // Products
            $products = [
                [
                    'entity_id' => '1',
                    'name' => 'Test Product 1',
                    'price' => 99.99,
                    'sku' => 'TEST-1',
                    'category_ids' => [3]
                ],
                [
                    'entity_id' => '2',
                    'name' => 'Test Product 2',
                    'price' => 149.99,
                    'sku' => 'TEST-2',
                    'category_ids' => [3]
                ]
            ];

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

            // Create buckets matching requested aggregation names
            $requestedAggs = $request->getAggregation();
            $buckets = [];
            
            foreach ($requestedAggs as $name => $agg) {
                $this->logger->debug("Processing aggregation: $name");
                $bucketName = $agg->getName();
                
                if (strpos($bucketName, 'price') !== false) {
                    $buckets[$bucketName] = new \Magento\Framework\Search\Response\Bucket(
                        $bucketName,
                        [
                            new Value('90_100', [
                                'from' => 90,
                                'to' => 100,
                                'count' => 1,
                                'value' => '90_100'
                            ], $bucketName),
                            new Value('140_150', [
                                'from' => 140,
                                'to' => 150,
                                'count' => 1,
                                'value' => '140_150'
                            ], $bucketName)
                        ]
                    );
                } elseif (strpos($bucketName, 'category') !== false) {
                    $buckets[$bucketName] = new \Magento\Framework\Search\Response\Bucket(
                        $bucketName,
                        [
                            new Value(3, [
                                'value' => 3,
                                'count' => 2
                            ], $bucketName)
                        ]
                    );
                }
            }

            // Log the buckets we're creating
            $this->logger->debug('Created buckets with names: ' . print_r(array_keys($buckets), true));

            $aggregations = new Aggregation($buckets);
            return new QueryResponse($documents, $aggregations, count($documents));

        } catch (\Exception $e) {
            $this->logger->error("SearchEnginePlugin Error: " . $e->getMessage());
            throw $e;
        }
    }
}