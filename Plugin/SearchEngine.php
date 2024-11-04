<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Api\Search\Document as SearchDocument;
use Magento\Framework\Search\Response\QueryResponse;
use Magento\Framework\Search\Response\Aggregation;
use Magento\Framework\Search\Response\Aggregation\Value;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\RequestInterface as HttpRequestInterface;

class SearchEngine extends \Magento\Search\Model\SearchEngine
{
    protected $logger;
    protected $httpRequest;
    protected $parentSearchEngine;

    public function __construct(
        LoggerInterface $logger,
        HttpRequestInterface $httpRequest,
        \Magento\Search\Model\SearchEngine $parentSearchEngine
    ) {
        $this->logger = $logger;
        $this->httpRequest = $httpRequest;
        $this->parentSearchEngine = $parentSearchEngine;
    }

    public function search(RequestInterface $request)
    {
        // Check if override parameter exists
        if (!$this->httpRequest->getParam('gpSearchOverride')) {
            var_dump("USING DEFAULT MAGENTO SEARCH");
            return $this->parentSearchEngine->search($request);
        }

        var_dump("USING CUSTOM SEARCH ENGINE");
        
        try {
            // Create multiple products for testing
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

                var_dump("Creating document for product:", $product['entity_id']);

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

            // Create aggregations
            $aggregations = new Aggregation(
                [
                    'price_bucket' => new Value(99.99, 'price'),
                ],
                [
                    'price_bucket' => [
                        'name' => 'price_bucket',
                        'values' => [
                            [
                                'from' => 0,
                                'to' => 100,
                                'count' => 1
                            ],
                            [
                                'from' => 100,
                                'to' => 200,
                                'count' => 1
                            ]
                        ]
                    ]
                ]
            );

            var_dump("Created documents count:", count($documents));

            $response = new QueryResponse(
                $documents,
                $aggregations,
                count($documents)
            );

            var_dump("RESPONSE CREATED WITH " . count($documents) . " DOCUMENTS");

            return $response;

        } catch (\Exception $e) {
            var_dump("Error in search engine:", $e->getMessage());
            throw $e;
        }
    }
}