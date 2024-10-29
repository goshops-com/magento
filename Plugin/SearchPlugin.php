<?php
namespace Gopersonal\Magento\Plugin;

class SearchPlugin
{
    public function aroundLoad(
        \Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection $subject,
        \Closure $proceed,
        $printQuery = false,
        $logQuery = false
    ) {
        // Reset the WHERE clause
        $subject->getSelect()->reset(\Zend_Db_Select::WHERE);
        // Add your hardcoded product IDs
        $subject->getSelect()->where('e.entity_id IN (?)', [2040]);

        // Proceed with the load
        return $proceed($printQuery, $logQuery);
    }
}
