<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Framework\Search\Response\QueryResponse;
use Psr\Log\LoggerInterface;

class SearchResultPlugin
{
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function afterGetAggregations(QueryResponse $subject, $result)
    {
        $bucketNames = [];
        foreach ($result->getBuckets() as $bucket) {
            $bucketNames[] = $bucket->getName();
        }
        $this->logger->debug(
            'Available bucket names in search response: ' .
                json_encode($bucketNames)
        );
        return $result;
    }
}
