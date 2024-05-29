<?php

namespace Gopersonal\Magento\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as SearchCollection;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;
use Zend_Db_Select;

class SearchOverride
{
    protected $resourceConnection;
    protected $logger;
    protected $request;
    const FLAG_KEY = 'search_override_executed';

    public function __construct(
        ResourceConnection $resourceConnection,
        LoggerInterface $logger,
        RequestInterface $request
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
        $this->request = $request;
    }

    protected function getCurrentUrl()
    {
        return $this->request->getUriString();
    }

    public function afterLoad(SearchCollection $subject)
    {
        $currentUrl = $this->getCurrentUrl();

        // Check if there is a search term
        if (!$this->request->getParam('q')) {
            $this->logger->info('AfterLoad Execution Skipped (No Search Term) URL: ' . $currentUrl);
            return $subject;
        }

        // Check if the flag is set to prevent multiple executions
        if ($this->request->getParam(self::FLAG_KEY)) {
            $this->logger->info('AfterLoad Execution Skipped (Already Executed) URL: ' . $currentUrl);
            return $subject;
        }

        // Set the flag to indicate the plugin has been executed
        $this->request->setParam(self::FLAG_KEY, true);

        $fixedProductIds = [1556]; // Your array of fixed product IDs (or fetch dynamically)

        // Log the query before modification
        $this->logger->info('Original Query in afterLoad: ' . $subject->getSelect()->__toString() . ' URL: ' . $currentUrl);

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
            $this->logger->info('Modified Query in afterLoad: ' . $subject->getSelect()->__toString() . ' URL: ' . $currentUrl);
            $this->logger->info('FROM Part in afterLoad: ' . print_r($subject->getSelect()->getPart(Zend_Db_Select::FROM), true) . ' URL: ' . $currentUrl);
            $this->logger->info('WHERE Part in afterLoad: ' . print_r($subject->getSelect()->getPart(Zend_Db_Select::WHERE), true) . ' URL: ' . $currentUrl);

            // Check for NULL condition and remove it
            $whereParts = $subject->getSelect()->getPart(Zend_Db_Select::WHERE);
            foreach ($whereParts as $key => $wherePart) {
                if (strpos($wherePart, '(NULL)') !== false) {
                    unset($whereParts[$key]);
                }
            }
            $subject->getSelect()->setPart(Zend_Db_Select::WHERE, $whereParts);
        }

        // Log the final executed query
        $this->logger->info('Final Executed Query in afterLoad: ' . $subject->getSelect()->__toString() . ' URL: ' . $currentUrl);

        // Log the result count
        $this->logger->info('Result Count in afterLoad: ' . count($subject->getItems()) . ' URL: ' . $currentUrl);

        // Log the product IDs in the result
        $productIds = [];
        foreach ($subject->getItems() as $item) {
            $productIds[] = $item->getId();
        }
        $this->logger->info('Result Product IDs in afterLoad: ' . implode(', ', $productIds) . ' URL: ' . $currentUrl);

        return $subject;
    }
}
