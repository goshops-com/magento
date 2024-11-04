<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Framework\Search\ResponseInterface;
use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\Document;
use Magento\Framework\Search\Response\QueryResponse;
use Magento\Framework\Search\Response\Aggregation;
use Magento\Framework\Search\Response\Aggregation\Value;

class SearchEngine extends \Magento\Search\Model\SearchEngine
{
    public function search(RequestInterface $request)
    {
        return new QueryResponse(
            [
                new Document(
                    2040,
                    ['score' => new Value(1)]
                )
            ],
            new Aggregation([], [])
        );
    }
}