<?php
namespace Gopersonal\Magento\Plugin;

class SearchPlugin
{
    public function aroundAddSearchFilter(
        \Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection $subject,
        \Closure $proceed,
        $query
    ) {
        // Prevent the search filter from being applied
        return $subject;
    }

    public function beforeLoad(\Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection $subject)
    {
        // Reset existing query conditions
        $subject->getSelect()->reset(\Zend_Db_Select::WHERE);
        $subject->getSelect()->reset(\Zend_Db_Select::HAVING);
        $subject->getSelect()->reset(\Zend_Db_Select::FROM);
        $subject->getSelect()->reset(\Zend_Db_Select::GROUP);
        $subject->getSelect()->reset(\Zend_Db_Select::JOIN);

        // Rebuild the FROM clause to ensure base table is set correctly
        $subject->getSelect()->from(
            ['e' => $subject->getTable('catalog_product_entity')],
            ['entity_id']
        );

        // Add your hardcoded product IDs
        $subject->getSelect()->where('e.entity_id IN (?)', [2040]);

        return null;
    }
}
