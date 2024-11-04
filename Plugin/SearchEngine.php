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
    protected $logger;
    protected $httpRequest;
    protected $objectManager;
    protected $adapterFactory;
    protected $intervalFactory;

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
        if (!$this->httpRequest->getParam('gpSearchOverride')) {
            $this->logger->info("USING DEFAULT MAGENTO SEARCH");
            return parent::search($request);
        }

        $this->logger->info("USING CUSTOM SEARCH ENGINE");
        
        try {
            // Get aggregations from request
            $requestAggregations = $request->getAggregation();
            
            // Log the request facets
            $this->logger->info("Requested Aggregations:");
            foreach ($requestAggregations as $aggregation) {
                $this->logger->info(print_r([
                    'name' => $aggregation->getName(),
                    'type' => $aggregation->getType(),
                    'field' => $aggregation->getField(),
                    'metrics' => $aggregation->getMetrics()
                ], true));
            }

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
                        'score' => new Value(1, 'score')
                    ]
                );
            }

            // Prepare bucket data for aggregations
            $bucketData = [
                'price_bucket' => [
                    'name' => 'price_bucket',
                    'values' => [
                        ['from' => 0, 'to' => 100, 'count' => 1],
                        ['from' => 100, 'to' => 200, 'count' => 1]
                    ]
                ],
                'category_bucket' => [
                    'name' => 'category_bucket',
                    'values' => [
                        ['value' => 2, 'count' => 1],
                        ['value' => 3, 'count' => 1]
                    ]
                ]
            ];

            $aggregations = new Aggregation(
                [
                    'price_bucket' => new Value(99.99, 'price'),
                    'category_bucket' => new Value(2, 'category')
                ],
                $bucketData
            );

            return new QueryResponse($documents, $aggregations, count($documents));

        } catch (\Exception $e) {
            $this->logger->error("Error in search engine: " . $e->getMessage());
            throw $e;
        }
    }
}