<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Api\Search\Document as SearchDocument;
use Magento\Framework\Search\Response\QueryResponse;
use Magento\Framework\Search\Response\Aggregation;
use Magento\Framework\Search\Response\Aggregation\Value;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\RequestInterface as HttpRequestInterface;
use Magento\Elasticsearch\SearchAdapter\SearchIndexNameResolver;
use Magento\Elasticsearch\Model\Client\ClientInterface;

class SearchEngine extends \Magento\Search\Model\SearchEngine
{
    protected $logger;
    protected $httpRequest;
    protected $searchIndexResolver;
    protected $elasticsearchClient;

    public function __construct(
        LoggerInterface $logger,
        HttpRequestInterface $httpRequest,
        SearchIndexNameResolver $searchIndexResolver,
        ClientInterface $elasticsearchClient
    ) {
        $this->logger = $logger;
        $this->httpRequest = $httpRequest;
        $this->searchIndexResolver = $searchIndexResolver;
        $this->elasticsearchClient = $elasticsearchClient;
    }

    public function search(RequestInterface $request)
    {
        if (!$this->httpRequest->getParam('gpSearchOverride')) {
            var_dump("USING DEFAULT MAGENTO SEARCH");
            // Call Elasticsearch directly instead of using parent
            $indexName = $this->searchIndexResolver->getIndexName();
            $result = $this->elasticsearchClient->query($this->buildDefaultQuery($request));
            return $this->convertElasticsearchResponse($result);
        }

        var_dump("USING CUSTOM SEARCH ENGINE");
        
        try {
            // Your custom search implementation
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

            $aggregations = new Aggregation(
                [
                    'price_bucket' => new Value(99.99, 'price'),
                ],
                [
                    'price_bucket' => [
                        'name' => 'price_bucket',
                        'values' => [
                            ['from' => 0, 'to' => 100, 'count' => 1],
                            ['from' => 100, 'to' => 200, 'count' => 1]
                        ]
                    ]
                ]
            );

            return new QueryResponse($documents, $aggregations, count($documents));

        } catch (\Exception $e) {
            var_dump("Error in search engine:", $e->getMessage());
            throw $e;
        }
    }

    private function buildDefaultQuery(RequestInterface $request)
    {
        // Build the elasticsearch query based on the request
        // This is a basic example - you'll need to adapt this based on your needs
        return [
            'index' => $this->searchIndexResolver->getIndexName(),
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['match_all' => new \stdClass()]
                        ]
                    ]
                ]
            ]
        ];
    }

    private function convertElasticsearchResponse($elasticsearchResponse)
    {
        // Convert Elasticsearch response to Magento's QueryResponse format
        $documents = [];
        if (isset($elasticsearchResponse['hits']['hits'])) {
            foreach ($elasticsearchResponse['hits']['hits'] as $hit) {
                $documentData = array_merge(
                    ['_id' => $hit['_id'], '_score' => $hit['_score']],
                    $hit['_source']
                );
                
                $documents[] = new SearchDocument(
                    $documentData,
                    ['score' => new Value($hit['_score'], 'score')]
                );
            }
        }

        return new QueryResponse(
            $documents,
            new Aggregation([], []),
            isset($elasticsearchResponse['hits']['total']['value']) 
                ? $elasticsearchResponse['hits']['total']['value'] 
                : 0
        );
    }
}