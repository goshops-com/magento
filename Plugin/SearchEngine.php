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

        // Mock search results - using proper document structure
        $documentData = [
            'entity_id' => '1',
            'score' => 1,
            '_id' => 1,
            '_score' => 1,
            '_type' => 'product',
            '_index' => 'catalog_product',
            '_source' => [
                'entity_id' => '1',
                'status' => 1,
                'visibility' => 4,
                'name' => 'Test Product',
                'sku' => 'TEST-1',
                'price' => 99.99,
                'score' => 1
            ],
            'custom_attributes' => [
                'score' => 1,
                'name' => 'Test Product',
                'status' => 1,
                'visibility' => 4,
                'price' => 99.99
            ]
        ];

        var_dump("DOCUMENT DATA:", $documentData);

        // Create the document with all required fields
        $document = new SearchDocument(
            $documentData,
            [
                'score' => new Value(1, 'score'),
                'name' => new Value('Test Product', 'name'),
                'status' => new Value(1, 'status'),
                'visibility' => new Value(4, 'visibility'),
                'price' => new Value(99.99, 'price')
            ]
        );

        // Create response with documents and aggregations
        $response = new QueryResponse(
            [$document],
            new Aggregation(
                ['price' => new Value(99.99, 'price')],
                ['price' => ['count' => 1, 'max' => 99.99, 'min' => 99.99]]
            ),
            1
        );

        var_dump("RESPONSE CREATED WITH 1 DOCUMENT");

        return $response;
    }
}