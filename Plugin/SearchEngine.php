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
        var_dump("REQUEST DATA:", $request->toArray());

        // Create document with minimum required fields
        $documentData = [
            'entity_id' => '1',
            'id' => '1', // Added id field
            'score' => 1,
            '_id' => 1,
            '_score' => 1,
            '_type' => 'product',
            '_index' => 'catalog_product',
            'store_id' => 1, // Added store_id
            'visibility' => 4,
            'status' => 1,
            '_source' => [
                'entity_id' => '1',
                'id' => '1', // Added id field in source
                'status' => 1,
                'visibility' => 4,
                'name' => 'Test Product',
                'sku' => 'TEST-1',
                'price' => 99.99,
                'score' => 1,
                'store_id' => 1, // Added store_id in source
                'type_id' => 'simple', // Added product type
                'website_ids' => [1], // Added website IDs
            ],
            'custom_attributes' => [
                'score' => 1,
                'name' => 'Test Product',
                'status' => 1,
                'visibility' => 4,
                'price' => 99.99,
                'store_id' => 1,
                'website_id' => 1
            ]
        ];

        var_dump("DOCUMENT DATA:", $documentData);

        // Create document with all field values explicitly set
        $attributeValues = [
            'id' => new Value('1', 'id'),
            'entity_id' => new Value('1', 'entity_id'),
            'score' => new Value(1, 'score'),
            'status' => new Value(1, 'status'),
            'visibility' => new Value(4, 'visibility'),
            'name' => new Value('Test Product', 'name'),
            'price' => new Value(99.99, 'price'),
            'store_id' => new Value(1, 'store_id'),
            'website_id' => new Value(1, 'website_id'),
            'type_id' => new Value('simple', 'type_id')
        ];

        $document = new SearchDocument($documentData, $attributeValues);

        // Create basic aggregations
        $aggregations = [
            'price_bucket' => new Value(99.99, 'price'),
            'category_bucket' => new Value(2, 'category_ids'),
        ];

        $buckets = [
            'price_bucket' => [
                'type' => 'dynamicBucket',
                'name' => 'price_bucket',
                'values' => [
                    ['value' => 99.99, 'count' => 1]
                ]
            ],
            'category_bucket' => [
                'type' => 'termBucket',
                'name' => 'category_bucket',
                'values' => [
                    ['value' => '2', 'count' => 1]
                ]
            ]
        ];

        $response = new QueryResponse(
            [$document],
            new Aggregation($aggregations, $buckets),
            1
        );

        var_dump("RESPONSE CREATED WITH 1 DOCUMENT");

        return $response;
    }
}