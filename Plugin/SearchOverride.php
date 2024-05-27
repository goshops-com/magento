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

    public function aroundGetSelect(
        SearchCollection $subject,
        \Closure $proceed
    ) {
        $fixedProductIds = [123, 456, 789]; // Your array of fixed product IDs (or fetch dynamically)

        if (!empty($fixedProductIds)) {
            $subject->getSelect()->orWhere('e.entity_id IN (?)', $fixedProductIds);
        }
        
        return $proceed(); // Let the original search query proceed with the added condition (if any)
    }
}
