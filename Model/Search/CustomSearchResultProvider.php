<?php
namespace Gopersonal\Magento\Model\Search;

use Magento\Framework\Search\SearchEngineInterface;
use Magento\Framework\Search\RequestInterface;

class CustomSearchResultProvider implements SearchEngineInterface
{
    public function search(RequestInterface $request)
    {
        // Debug to see if we're hitting this
        die('Custom search provider called!');

        $documents = [
            [
                'entity_id' => 2040,
                'score' => 1
            ]
        ];

        return new \Magento\Framework\Search\Response\QueryResponse(
            $documents,
            []  // empty aggregations
        );
    }
}