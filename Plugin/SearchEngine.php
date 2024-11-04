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
                'visibility' => 3,  // Changed to match filter value
                'status' => 1,
                'name' => 'Joust Duffle Bag',
                'sku' => 'test-sku',
                'description' => 'test desc',
                'short_description' => 'test short',
                'manufacturer' => 'test',
                'url_key' => 'test-url',
                'tax_class_id' => 1
            ],
            ['score' => new \Magento\Framework\Search\Response\Aggregation\Value(1.0, 'value')]
        );
        
        $response = new QueryResponse(
            [$document],
            new \Magento\Framework\Search\Response\Aggregation([], []),
            1
        );

        var_dump($response);
        
        return $response;
    }
}