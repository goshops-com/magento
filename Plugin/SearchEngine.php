<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Api\Search\Document as SearchDocument;
use Magento\Framework\Search\Response\QueryResponse;
use Magento\Framework\Search\Response\Aggregation;
use Magento\Framework\Search\Response\Aggregation\Value;
use Psr\Log\LoggerInterface;

class SearchEngine extends \Magento\Search\Model\SearchEngine
{
    protected $logger;

    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    public function search(RequestInterface $request)
    {
        var_dump("SEARCH ENGINE CALLED");

        // Extract search terms
        $queries = $request->getQuery()->getShould() ?: $request->getQuery()->getMust();
        $searchTerms = [];
        foreach ($queries as $query) {
            if ($query instanceof \Magento\Framework\Search\Request\Query\Match) {
                $searchTerms[] = $query->getValue();
            }
        }

        var_dump("SEARCH TERMS:", $searchTerms);

        // Create mock search results
        $searchResults = [
            [
                'entity_id' => '1',
                'name' => 'Product 1',
                'score' => 1.0
            ],
            [
                'entity_id' => '2',
                'name' => 'Product 2',
                'score' => 0.8
            ]
        ];

        var_dump("SEARCH RESULTS:", $searchResults);

        // Convert to documents
        $documents = [];
        foreach ($searchResults as $result) {
            $documentData = [
                'entity_id' => $result['entity_id'],
                '_id' => $result['entity_id'],
                '_source' => [
                    'entity_id' => $result['entity_id'],
                    'status' => 1,
                    'visibility' => 4,
                    'score' => $result['score']
                ]
            ];

            var_dump("DOCUMENT DATA:", $documentData);

            $documents[] = new SearchDocument(
                $documentData,
                ['score' => new Value($result['score'], 'value')]
            );
        }

        $response = new QueryResponse(
            $documents,
            new Aggregation([], []),
            count($documents)
        );

        var_dump("RESPONSE CREATED WITH " . count($documents) . " DOCUMENTS");

        return $response;
    }
}