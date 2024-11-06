<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\Response\QueryResponse;
use Magento\Framework\Search\Response\Aggregation;
use Magento\Framework\Search\Response\Aggregation\Value;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\RequestInterface as HttpRequestInterface;
use Magento\Search\Model\SearchEngine;
use Magento\Framework\ObjectManagerInterface;

class SearchEngine
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
        if (!$this->httpRequest->getParam('gpSearchOverride')) {
            $this->logger->debug("USING DEFAULT MAGENTO SEARCH");
            return $proceed($request);
        }

        $this->logger->debug("USING CUSTOM SEARCH ENGINE");
        
        try {
            // Your existing products array
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
                    '_source' => [
                        'entity_id' => $product['entity_id'],
                        'name' => $product['name'],
                        'sku' => $product['sku'],
                        'price' => $product['price'],
                        'status' => 1,
                        'visibility' => 4,
                        'type_id' => 'simple',
                        'store_id' => 1
                    ]
                ];

                $documents[] = new \Magento\Framework\Search\Document(
                    $product['entity_id'],
                    [
                        'entity_id' => new Value($product['entity_id']),
                        'name' => new Value($product['name']),
                        'price' => new Value($product['price']),
                        'sku' => new Value($product['sku']),
                        'status' => new Value(1),
                        'visibility' => new Value(4),
                        'store_id' => new Value(1),
                        'score' => new Value(1)
                    ]
                );
            }

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