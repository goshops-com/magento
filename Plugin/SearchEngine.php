<?php

namespace Gopersonal\Magento\Plugin;

use Magento\Framework\Search\SearchEngineInterface;
use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Api\Search\Document as SearchDocument;
use Magento\Framework\Search\Response\QueryResponse;
use Magento\Framework\Search\Response\AggregationFactory;
use Psr\Log\LoggerInterface;

class SearchEngine implements SearchEngineInterface
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

    public function search(RequestInterface $request)
    {
        $this->logger->debug("SEARCH ENGINE CALLED23");

        $documentData = [
            'entity_id' => '1',
            'status' => 1,
            'visibility' => 4,
            'score' => 1
        ];

        $this->logger->debug("DOCUMENT DATA:", $documentData);

        $document = new SearchDocument(
            '1',
            $documentData
        );

        $aggregations = $this->aggregationFactory->create([]);

        $response = new QueryResponse(
            [$document],
            $aggregations,
            1
        );

        return $response;
    }

    // Implement any other methods required by the interface
    public function getIds(QueryResponse $response)
    {
        // Implement this method based on your needs
        return [1];
    }
}
