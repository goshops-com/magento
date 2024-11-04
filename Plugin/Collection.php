<?php
namespace Gopersonal\Magento\Plugin;

class Collection extends \Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection
{
    protected function _renderFiltersBefore()
    {
        var_dump("COLLECTION RENDER FILTERS");
        
        $this->_productLimitationPrice();

        parent::_renderFiltersBefore();
        
        var_dump("AFTER PARENT RENDER");
        var_dump($this->getItems());
    }
}