<?php

namespace Gopersonal\Magento\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as SearchCollection;
use Magento\Framework\App\ResourceConnection;

class SearchOverride
{
    protected $resourceConnection;

    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    public function aroundLoad(
        SearchCollection $subject,
        \Closure $proceed,
        $printQuery = false,
        $logQuery = false
    ) {
        $fixedProductIds = [1556]; // Your array of fixed product IDs (or fetch dynamically)

        if (!empty($fixedProductIds)) {
            // Reset the select query to include only the fixed product IDs
            $subject->getSelect()->reset(\Zend_Db_Select::WHERE);
            $subject->getSelect()->reset(\Zend_Db_Select::ORDER);
            $subject->getSelect()->reset(\Zend_Db_Select::LIMIT_COUNT);
            $subject->getSelect()->reset(\Zend_Db_Select::LIMIT_OFFSET);
            $subject->getSelect()->reset(\Zend_Db_Select::FROM);
            $subject->getSelect()->from(
                ['e' => $subject->getTable('catalog_product_entity')],
                ['entity_id']
            );
            $subject->getSelect()->where('e.entity_id IN (?)', $fixedProductIds);
        }

        return $proceed($printQuery, $logQuery); // Let the original load method proceed with the modified query
    }
}
