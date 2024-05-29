<?php

namespace Gopersonal\Magento\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as SearchCollection;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class SearchOverride
{
    protected $resourceConnection;
    protected $logger;

    // Static counter to track plugin invocations
    protected static $invocationCount = 0;

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
        self::$invocationCount++;
        $this->logger->info('Plugin Invocation Count: ' . self::$invocationCount);

        $fixedProductIds = [1556]; // Your array of fixed product IDs (or fetch dynamically)

        // Log the query before modification
        $this->logger->info('Original Query: ' . $subject->getSelect()->__toString());

        if (!empty($fixedProductIds)) {
            // Reset the WHERE clause of the query
            $subject->getSelect()->reset(\Zend_Db_Select::WHERE);
            // Rebuild the WHERE clause to include only the fixed product IDs
            $subject->getSelect()->where('e.entity_id IN (?)', $fixedProductIds);

            // Check if columns have already been set to avoid redundancy
            $columns = $subject->getSelect()->getPart(\Zend_Db_Select::COLUMNS);
            $columnsSet = false;
            foreach ($columns as $column) {
                if ($column[1] == 'e.*') {
                    $columnsSet = true;
                    break;
                }
            }

            if (!$columnsSet) {
                // Ensure necessary columns are included only once
                $subject->getSelect()->reset(\Zend_Db_Select::COLUMNS);
                $subject->getSelect()->columns('e.*');
            }

            // Reset ordering and pagination if necessary
            $subject->getSelect()->reset(\Zend_Db_Select::ORDER); // Reset any ordering
            $subject->setPageSize(false); // Remove any existing page size limit
            $subject->setCurPage(false);  // Remove any existing current page

            // Log the modified query for debugging
            $this->logger->info('Modified Query: ' . $subject->getSelect()->__toString());
        }

        // Proceed with the original load method
        $result = $proceed($printQuery, $logQuery);

        // Log the final executed query
        $this->logger->info('Final Executed Query: ' . $subject->getSelect()->__toString());

        // Log the result count
        $this->logger->info('Result Count: ' . count($subject->getItems()));

        // Log the product IDs in the result
        $productIds = [];
        foreach ($subject->getItems() as $item) {
            $productIds[] = $item->getId();
        }
        $this->logger->info('Result Product IDs: ' . implode(', ', $productIds));

        return $result;
    }
}
