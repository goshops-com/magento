<?php

namespace Gopersonal\Magento\Plugin;

use Magento\Framework\Api\Search\Document as SearchDocument;
use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\Response\QueryResponse;
use Magento\Framework\Search\Response\AggregationFactory;
use Psr\Log\LoggerInterface;

class SearchEnginePlugin
{
    protected $logger;
    protected $aggregationFactory;

    public function __construct(
        LoggerInterface $logger,
        AggregationFactory $aggregationFactory
    ) {
        $this->logger = $logger;
        $this->aggregationFactory = $aggregationFactory;
    }

    public function aroundSearch(
        \Magento\Search\Model\SearchEngine $subject,
        callable $proceed,
        RequestInterface $request
    ) {
        $this->logger->debug("SEARCH ENGINE PLUGIN CALLED");

        $documentData = [
            'id' => '1',
            'entity_id' => '1',
            'status' => 1,
            'visibility' => 4,
            'score' => 1
        ];

        $this->logger->debug("DOCUMENT DATA:", $documentData);

        $document = new SearchDocument($documentData);

        $aggregations = $this->aggregationFactory->create([]);

        $response = new QueryResponse(
            [$document],
            $aggregations,
            1
        );

        return $response;
    }
}
