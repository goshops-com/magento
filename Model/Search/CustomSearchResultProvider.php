<?php
namespace Gopersonal\Magento\Model\Search;

use Magento\Framework\Search\Response\QueryResponse;
use Magento\Framework\Search\Response\Aggregation;
use Magento\Framework\Search\Document;
use Magento\Framework\Search\DocumentField;

class CustomSearchResultProvider extends QueryResponse 
{
    public function __construct()
    {
        $documents = [
            new Document(
                '2040',
                ['entity_id' => new DocumentField('entity_id', 2040)]
            )
        ];
        
        parent::__construct($documents, new Aggregation([]));
    }
}