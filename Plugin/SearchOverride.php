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
        $fixedProductIds = [1556]; 

        if (!empty($fixedProductIds)) {
            $select = $subject->getSelect();

            // 1. Create a UNION query
            $unionSelect = $this->resourceConnection->getConnection()
                ->select()
                ->from(['e' => $subject->getMainTable()], ['entity_id'])
                ->where('e.entity_id IN (?)', $fixedProductIds);

            // 2. Join the original query and the UNION query
            $select->union([$unionSelect], Select::SQL_UNION_ALL);

            // 3. Order the results to prioritize the fixed products
            $select->order('e.entity_id = 1556 DESC'); // Prioritize the fixed product
        }

        return $proceed($printQuery, $logQuery);
    }
}