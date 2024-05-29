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
            // Reset the query parts
            $subject->getSelect()->reset(\Zend_Db_Select::WHERE);
            $subject->getSelect()->reset(\Zend_Db_Select::FROM);
            $subject->getSelect()->reset(\Zend_Db_Select::COLUMNS);
            $subject->getSelect()->reset(\Zend_Db_Select::ORDER);
            $subject->getSelect()->reset(\Zend_Db_Select::LIMIT_COUNT);
            $subject->getSelect()->reset(\Zend_Db_Select::LIMIT_OFFSET);

            // Rebuild the query with only your fixed product IDs
            $subject->getSelect()->from(['e' => $subject->getMainTable()])
                ->where('e.entity_id IN (?)', $fixedProductIds);

            // Optionally, reset other parts of the query if needed
            $subject->setPageSize(false); // Remove any existing page size limit
            $subject->setCurPage(false);  // Remove any existing current page

            // Proceed with the modified query
            return $proceed($printQuery, $logQuery);
        }

        // If no fixed product IDs, proceed with the original query
        return $proceed($printQuery, $logQuery);
    }
}
