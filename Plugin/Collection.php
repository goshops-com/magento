<?php
namespace Gopersonal\Magento\Plugin;

class Collection extends \Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection
{
    protected function _renderFiltersBefore()
    {
        var_dump("COLLECTION RENDER FILTERS BEFORE");
        
        parent::_renderFiltersBefore();
        
        var_dump("COLLECTION AFTER PARENT");
        var_dump("Current IDs:", $this->_loadedIds);
        var_dump("Search Result:", $this->searchResult);
    }
}