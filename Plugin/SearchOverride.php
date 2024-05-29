<?php

namespace Gopersonal\Magento\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as SearchCollection;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;
use Zend_Db_Select;

class SearchOverride
{
    protected $resourceConnection;
    protected $logger;
    protected static $isProcessing = false; // Prevent recursion

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
        if (self::$isProcessing) {
            // Prevent recursion
            return $proceed($printQuery, $logQuery);
        }

        self::$isProcessing = true;

        $fixedProductIds = [1556]; // Your array of fixed product IDs (or fetch dynamically)

        // Log the query before modification
        $this->logger->info('Original Query: ' . $subject->getSelect()->__toString());

        if (!empty($fixedProductIds)) {
            // Reset the WHERE clause of the query
            $subject->getSelect()->reset(Zend_Db_Select::WHERE);
            // Rebuild the WHERE clause to include only the fixed product IDs
            $subject->getSelect()->where('e.entity_id IN (?)', $fixedProductIds);

            // Ensure necessary columns are included only once
            $columns = $subject->getSelect()->getPart(Zend_Db_Select::COLUMNS);
            $columnsSet = false;
            foreach ($columns as $column) {
                if ($column[1] == 'e.*') {
                    $columnsSet = true;
                    break;
                }
            }

            if (!$columnsSet) {
                $subject->getSelect()->reset(Zend_Db_Select::COLUMNS);
                $subject->getSelect()->columns('e.*');
            }

            // Reset ordering and pagination if necessary
            $subject->getSelect()->reset(Zend_Db_Select::ORDER); // Reset any ordering
            $subject->setPageSize(false); // Remove any existing page size limit
            $subject->setCurPage(false);  // Remove any existing current page

            // Log detailed query parts for debugging
            $this->logger->info('Modified Query: ' . $subject->getSelect()->__toString());
            $this->logger->info('FROM Part: ' . print_r($subject->getSelect()->getPart(Zend_Db_Select::FROM), true));
            $this->logger->info('INNER JOIN Part: ' . print_r($subject->getSelect()->getPart(Zend_Db_Select::INNER_JOIN), true));
            $this->logger->info('LEFT JOIN Part: ' . print_r($subject->getSelect()->getPart(Zend_Db_Select::LEFT_JOIN), true));
            $this->logger->info('WHERE Part: ' . print_r($subject->getSelect()->getPart(Zend_Db_Select::WHERE), true));
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

        self::$isProcessing = false;

        return $result;
    }
}
