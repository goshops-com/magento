<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Framework\Search\ResponseInterface;
use Magento\Framework\Search\RequestInterface;

class SearchEngine extends \Magento\Search\Model\SearchEngine
{
    public function search(RequestInterface $request)
    {
        return new \Magento\Framework\Search\Response\QueryResponse(
            [
                new \Magento\Framework\Search\Document(
                    2040,
                    ['score' => new \Magento\Framework\Search\Response\Aggregation\Value(1)]
                )
            ],
            new \Magento\Framework\Search\Response\Aggregation([], [])
        );
    }
}