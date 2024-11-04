<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Api\Search\Document as SearchDocument;
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
        var_dump("SEARCH CALLED");
        
        $response = new \Magento\Framework\Search\Response\QueryResponse(
            [
                new SearchDocument(
                    ['entity_id' => 1],
                    ['score' => new \Magento\Framework\Search\Response\Aggregation\Value(1.0, 'value')]
                )
            ],
            new \Magento\Framework\Search\Response\Aggregation([], [])
        );

        var_dump("RESPONSE CREATED");
        
        return $response;
    }
}