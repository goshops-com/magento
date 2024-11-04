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
            $this->logger->info("USING DEFAULT MAGENTO SEARCH");
            return parent::search($request);
        }

        $this->logger->info("USING CUSTOM SEARCH ENGINE");
        
        try {
            // Log the original request aggregations
            $requestAggregations = $request->getAggregation();
            $this->logger->info("Original Request Aggregations:", ['aggregations' => print_r($requestAggregations, true)]);

            // Get search query
            $queryText = $request->getName();
            $this->logger->info("Search query: " . $queryText);

            // Add default buckets since original request might be empty
            $bucketData = [
                'category_bucket' => [
                    'name' => 'category_bucket',
                    'field' => 'category_ids',
                    'metrics' => ['count' => 1],
                    'values' => [
                        ['value' => 2, 'count' => 1],
                        ['value' => 3, 'count' => 1]
                    ]
                ],
                'price_bucket' => [
                    'name' => 'price_bucket',
                    'field' => 'price',
                    'metrics' => ['count' => 1],
                    'values' => [
                        ['from' => 0, 'to' => 100, 'count' => 1],
                        ['from' => 100, 'to' => 200, 'count' => 1]
                    ]
                ]
            ];

            // Rest of your existing code for $products and $documents...

            $aggregations = new Aggregation(
                [
                    'category_bucket' => new Value(2, 'category_ids'),
                    'price_bucket' => new Value(99.99, 'price')
                ],
                $bucketData
            );

            $response = new QueryResponse($documents, $aggregations, count($documents));
            
            // Log the response aggregations
            $this->logger->info("Response Aggregations:", [
                'buckets' => print_r($bucketData, true)
            ]);

            return $response;

        } catch (\Exception $e) {
            $this->logger->error("Error in search engine: " . $e->getMessage());
            throw $e;
        }
    }
}