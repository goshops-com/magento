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
        $fixedProductIds = [123, 456, 789]; // Your array of fixed product IDs (or fetch dynamically)

        if (!empty($fixedProductIds)) {
            $subject->getSelect()->where('e.entity_id IN (?)', $fixedProductIds);
        }

        return $proceed($printQuery, $logQuery); // Let the original load method proceed with the modified query
    }
}
