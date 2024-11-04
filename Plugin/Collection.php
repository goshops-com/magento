<?php
namespace Gopersonal\Magento\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as FulltextCollection;

class Collection extends FulltextCollection 
{
    protected function _renderFiltersBefore()
    {
        var_dump("===== COLLECTION START =====");
        $this->printStack();
        
        parent::_renderFiltersBefore();
        
        var_dump("===== COLLECTION END =====");
        var_dump($this->getSelect()->__toString());
    }

    private function printStack() 
    {
        $e = new \Exception();
        var_dump("Stack trace:", $e->getTraceAsString());
    }
}