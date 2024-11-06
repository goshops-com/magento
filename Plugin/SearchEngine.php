<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Framework\Search\RequestInterface;
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
    // ... your existing constructor and other methods ...

    public function search(RequestInterface $request)
    {
        if (!$this->httpRequest->getParam('gpSearchOverride')) {
            $this->logger->debug("USING DEFAULT MAGENTO SEARCH");
            return parent::search($request);
        }

        $this->logger->debug("USING CUSTOM SEARCH ENGINE");
        
        try {
            // Your existing products array and documents creation...

            // Create proper bucket values
            $priceBucketValues = [
                new Value('0_10', [
                    'from' => 0,
                    'to' => 10,
                    'count' => 1,
                    'value' => '0_10'
                ]),
                new Value('20_30', [
                    'from' => 20,
                    'to' => 30,
                    'count' => 2,
                    'value' => '20_30'
                ]),
                new Value('30_40', [
                    'from' => 30,
                    'to' => 40,
                    'count' => 3,
                    'value' => '30_40'
                ]),
                new Value('40_50', [
                    'from' => 40,
                    'to' => 50,
                    'count' => 2,
                    'value' => '40_50'
                ])
            ];

            $categoryBucketValues = [
                new Value(3, [
                    'value' => 3,
                    'count' => 7
                ])
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
            $this->logger->error("Error in search engine: " . $e->getMessage());
            throw $e;
        }
    }
}