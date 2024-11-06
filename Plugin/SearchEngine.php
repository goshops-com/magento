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
    private $logger;
    private $httpRequest;
    private $objectManager;
    private $adapterFactory;
    private $intervalFactory;

    public function __construct(
        AdapterFactory $adapterFactory,
        IntervalFactory $intervalFactory,
        LoggerInterface $logger,
        HttpRequestInterface $httpRequest,
        ObjectManagerInterface $objectManager
    ) {
        $this->adapterFactory = $adapterFactory;
        $this->intervalFactory = $intervalFactory;
        $this->logger = $logger;
        $this->httpRequest = $httpRequest;
        $this->objectManager = $objectManager;
        parent::__construct($adapterFactory, $intervalFactory);
    }

    public function search(RequestInterface $request)
    {
        $this->logger->debug("SearchEngine class search() called");
        
        if (!$this->httpRequest->getParam('gpSearchOverride')) {
            $this->logger->debug("SearchEngine: USING DEFAULT MAGENTO SEARCH");
            return parent::search($request);
        }

        $this->logger->debug("SearchEngine: USING CUSTOM SEARCH ENGINE");
        
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
                    'count' => 0,
                    'value' => '0_10'
                ]),
                new Value('90_100', [
                    'from' => 90,
                    'to' => 100,
                    'count' => 1,
                    'value' => '90_100'
                ]),
                new Value('140_150', [
                    'from' => 140,
                    'to' => 150,
                    'count' => 1,
                    'value' => '140_150'
                ])
            ];

            $categoryBucketValues = [
                new Value(3, [
                    'value' => 3,
                    'count' => 2
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
            $this->logger->error("SearchEngine Error: " . $e->getMessage());
            throw $e;
        }
    }
}