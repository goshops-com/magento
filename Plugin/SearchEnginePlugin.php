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
            if ($result instanceof QueryResponse) {
                // Log the complete structure of the default search response
                $this->logger->debug('Default search response structure: ' . print_r([
                    'total_count' => $result->getTotal(),
                    'aggregations' => array_map(function($bucket) {
                        return [
                            'name' => $bucket->getName(),
                            'field' => method_exists($bucket, 'getField') ? $bucket->getField() : 'N/A',
                            'metrics' => array_map(function($value) {
                                return [
                                    'value' => $value->getValue(),
                                    'metrics' => $value->getMetrics()
                                ];
                            }, $bucket->getValues())
                        ];
                    }, $result->getAggregations()->getBuckets())
                ], true));
            }
            return $result;
        }

        $this->logger->debug("SearchEnginePlugin: USING CUSTOM SEARCH ENGINE");
        
        try {
            // Get default response first to see its structure
            $defaultResponse = $proceed($request);
            if ($defaultResponse instanceof QueryResponse) {
                $this->logger->debug('Default buckets in custom search: ' . print_r(array_keys($defaultResponse->getAggregations()->getBuckets()), true));
            }

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

            // Use the same buckets from default response
            $buckets = [];
            if ($defaultResponse instanceof QueryResponse) {
                $originalBuckets = $defaultResponse->getAggregations()->getBuckets();
                foreach ($originalBuckets as $key => $originalBucket) {
                    if ($originalBucket->getName() === 'category') {
                        // Create category values
                        $categoryValues = [];
                        foreach ($products as $product) {
                            foreach ($product['category_ids'] as $catId) {
                                $categoryValues[$catId] = new Value($catId, [
                                    'value' => $catId,
                                    'count' => 1
                                ], 'category');
                            }
                        }
                        $buckets[$key] = new \Magento\Framework\Search\Response\Bucket(
                            'category',
                            array_values($categoryValues)
                        );
                    } else {
                        // Keep other buckets as they are
                        $buckets[$key] = $originalBucket;
                    }
                }
            }

            $aggregations = new Aggregation($buckets);
            $response = new QueryResponse($documents, $aggregations, count($documents));

            // Log the final response structure
            $this->logger->debug('Final custom response structure: ' . print_r([
                'total_count' => $response->getTotal(),
                'bucket_names' => array_map(function($bucket) {
                    return $bucket->getName();
                }, $response->getAggregations()->getBuckets())
            ], true));

            return $response;

        } catch (\Exception $e) {
            $this->logger->error("SearchEnginePlugin Error: " . $e->getMessage());
            throw $e;
        }
    }
}