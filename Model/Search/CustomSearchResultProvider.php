<?php
namespace Gopersonal\Magento\Model\Search;

use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Search\Response\QueryInterface;

class CustomSearchResultProvider extends \Magento\Framework\Search\Response\QueryResponse 
{
    public function __construct()
    {
        die('Custom Search Provider Called!');
        
        $documents = [
            [
                'entity_id' => 2040,
                'score' => 1
            ]
        ];
        
        parent::__construct($documents, []);
    }
}