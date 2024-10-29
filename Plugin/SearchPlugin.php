<?php
namespace Gopersonal\Magento\Plugin;

class SearchPlugin
{
    public function beforeLoad(\Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection $collection)
    {
        $collection->getSelect()->reset(\Zend_Db_Select::WHERE);
        $collection->getSelect()->where('e.entity_id IN (?)', [2040]);
        
        return null;
    }
}