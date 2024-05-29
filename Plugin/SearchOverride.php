<?php

namespace Gopersonal\Magento\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as SearchCollection;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class SearchOverride
{
    protected $resourceConnection;
    protected $logger;

    public function __construct(ResourceConnection $resourceConnection, LoggerInterface $logger)
    {
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
    }

    public function aroundLoad(
        SearchCollection $subject,
        \Closure $proceed,
        $printQuery = false,
        $logQuery = false
    ) {
        $fixedProductIds = [1556]; // Your array of fixed product IDs (or fetch dynamically)

        // Log the query before modification
        $this->logger->info('Original Query: ' . $subject->getSelect()->__toString());

        if (!empty($fixedProductIds)) {
            // Reset the WHERE clause of the query
            $subject->getSelect()->reset(\Zend_Db_Select::WHERE);
            // Rebuild the WHERE clause to include only the fixed product IDs
            $subject->getSelect()->where('e.entity_id IN (?)', $fixedProductIds);

            // Ensure necessary columns are included
            $subject->getSelect()->columns('*');

            // Reset ordering and pagination if necessary
            $subject->getSelect()->reset(\Zend_Db_Select::ORDER); // Reset any ordering
            $subject->setPageSize(false); // Remove any existing page size limit
            $subject->setCurPage(false);  // Remove any existing current page
        }

        // Log the query after modification
        $this->logger->info('Modified Query: ' . $subject->getSelect()->__toString());

        // Proceed with the original load method
        return $proceed($printQuery, $logQuery);
    }
}
