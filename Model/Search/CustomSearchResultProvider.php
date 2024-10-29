<?php
namespace Gopersonal\Magento\Model\Search;

use Magento\Framework\Search\Response\QueryResponse;
use Magento\Framework\Search\Response\Aggregation;

class CustomSearchResultProvider extends QueryResponse 
{
    public function __construct()
    {
        $documents = [
            [
                'entity_id' => 2040,
                'score' => 1
            ]
        ];
        
        parent::__construct($documents, new Aggregation([]));
    }
}
