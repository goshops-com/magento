<?php
namespace Gopersonal\Magento\Plugin;

class Collection extends \Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection
{
    protected function _renderFiltersBefore()
    {
        $searchTerm = $this->queryText;
        
        if ($searchTerm) {
            // Clear existing search condition but keep filters
            $this->getSelect()->reset(\Zend_Db_Select::WHERE);
            
            // Hardcode to product ID 2040
            $this->getSelect()->where('e.entity_id = ?', 2040);
        }
        
        return parent::_renderFiltersBefore();
    }
}