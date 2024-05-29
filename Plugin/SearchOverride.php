<?php

namespace Gopersonal\Magento\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as SearchCollection;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;

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
            // Resetting the where clause and joins to ensure only the fixed products are returned
            $select = $subject->getSelect();

            // Clear WHERE clause
            $wherePart = $select->getPart(Select::WHERE);
            foreach ($wherePart as $key => $condition) {
                if (strpos($condition, 'e.entity_id') === false) {
                    unset($wherePart[$key]);
                }
            }
            $select->setPart(Select::WHERE, $wherePart);

            // Apply new condition
            $select->where('e.entity_id IN (?)', $fixedProductIds);
        }

        // Proceed with the original load method
        return $proceed($printQuery, $logQuery);
    }
}
