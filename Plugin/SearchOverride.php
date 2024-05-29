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
            $connection = $this->resourceConnection->getConnection();
            $select = $connection->select()
                ->from(['e' => $subject->getMainTable()])
                ->where('e.entity_id IN (?)', $fixedProductIds);
            
            // Replace the original select with the new one
            $subject->setSelect($select);
        }

        // Proceed with the original load method
        return $proceed($printQuery, $logQuery);
    }
}
