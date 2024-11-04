<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Api\Search\Document as SearchDocument;

class SearchEngine extends \Magento\Search\Model\SearchEngine
{
    public function search(RequestInterface $request)
    {
        return new \Magento\Framework\Search\Response\QueryResponse(
            [
                new SearchDocument(
                    ['entity_id' => '2040'],
                    ['score' => new \Magento\Framework\Search\Response\Aggregation\Value(1.0, 'value')]
                )
            ],
            new \Magento\Framework\Search\Response\Aggregation([], [])
        );
    }
}