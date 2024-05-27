<?php
namespace Gopersonal\Magento\Model\ResourceModel\Fulltext;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as BaseCollection;

class Collection extends BaseCollection
{
    protected function _renderFiltersBefore()
    {
        // Override search functionality here
        $this->getSelect()->reset(\Magento\Framework\DB\Select::WHERE);
        $this->addFieldToFilter('entity_id', 1556); // Replace 1 with the hardcoded product ID you want to return

        // Call parent method to ensure aggregations work
        parent::_renderFiltersBefore();
    }
}
