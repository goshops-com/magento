<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Api\Search\Document as SearchDocument;
use Psr\Log\LoggerInterface;
use Magento\Framework\Search\Response\QueryResponse;

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
        
        // Create document with entity_id as string
        $documentData = [
            'entity_id' => '2040',  // Changed to string
            '_id' => 2040,
            '_source' => [
                'entity_id' => '2040',
                'status' => 1,
                'visibility' => 4,
                'score' => 1
            ]
        ];
        
        var_dump("DOCUMENT DATA:", $documentData);
        
        $document = new SearchDocument(
            $documentData,
            ['score' => new \Magento\Framework\Search\Response\Aggregation\Value(1.0, 'value')]
        );
        
        $response = new QueryResponse(
            [$document],
            new \Magento\Framework\Search\Response\Aggregation([], []),
            1
        );
        
        return $response;
    }
}