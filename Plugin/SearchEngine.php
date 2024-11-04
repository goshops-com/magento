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
        var_dump("SEARCH CALLED");
        
        $document = new SearchDocument(
            [
                'entity_id' => 2040,
                'score' => 1,
                'visibility' => 4,
                'status' => 1,
                '_id' => 2040    // Add _id field
            ],
            ['score' => new \Magento\Framework\Search\Response\Aggregation\Value(1.0, 'value')]
        );
        
        var_dump("DOCUMENT:", $document);
        
        $response = new QueryResponse(
            [$document],
            new \Magento\Framework\Search\Response\Aggregation([], []),
            1
        );

        var_dump("BEFORE RETURN");
        var_dump("Looking at _renderFiltersBefore...");
        $debug = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($debug as $d) {
            if (strpos($d['file'], 'Collection.php') !== false) {
                var_dump($d['file'] . ':' . $d['line']);
            }
        }
        
        return $response;
    }
}