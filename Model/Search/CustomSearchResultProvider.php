<?php
namespace Gopersonal\Magento\Model\Search;

use Magento\Framework\Search\Response\QueryResponse;
use Magento\Framework\Search\Response\Aggregation;
use Magento\Framework\Api\Search\Document;

class CustomSearchResultProvider extends QueryResponse 
{
   public function __construct()
   {
       $documents = [
           new Document(
               ['entity_id' => 2040],
               '2040'
           )
       ];
       
       parent::__construct($documents, new Aggregation([]));
   }
}