<?php
namespace Gopersonal\Magento\Model\Search;

use Magento\Framework\Search\Response\QueryResponse;
use Magento\Framework\Search\Response\Aggregation;
use Magento\Framework\Api\Search\Document;
use Magento\Framework\Api\AttributeValue;

class CustomSearchResultProvider extends QueryResponse 
{
    public function __construct()
    {
        $documents = [
            new Document(
                '2040', 
                [
                    'entity_id' => new AttributeValue([
                        'attribute_code' => 'entity_id',
                        'value' => 2040
                    ])
                ]
            )
        ];
        
        parent::__construct($documents, new Aggregation([]));
    }
}