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

    public function aroundLoad(
        SearchCollection $subject,
        \Closure $proceed,
        $printQuery = false,
        $logQuery = false
    ) {
        $currentUrl = $this->getCurrentUrl();

        if ($this->request->getParam(self::FLAG_KEY)) {
            // Prevent multiple executions
            $this->logger->info('Plugin Execution Skipped (Already Executed) URL: ' . $currentUrl);
            return $proceed($printQuery, $logQuery);
        }

        // Set the flag to indicate the plugin has been executed
        $this->request->setParam(self::FLAG_KEY, true);

        // Remove the 'q' parameter to prevent Magento's search logic from interfering
        $params = $this->request->getParams();
        unset($params['q']);
        $this->request->setParams($params);

        $fixedProductIds = [1556]; // Your array of fixed product IDs (or fetch dynamically)

        // Log the query before modification
        $this->logger->info('Original Query: ' . $subject->getSelect()->__toString() . ' URL: ' . $currentUrl);

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
            $this->logger->info('Modified Query: ' . $subject->getSelect()->__toString() . ' URL: ' . $currentUrl);
            $this->logger->info('FROM Part: ' . print_r($subject->getSelect()->getPart(Zend_Db_Select::FROM), true) . ' URL: ' . $currentUrl);
            $this->logger->info('WHERE Part: ' . print_r($subject->getSelect()->getPart(Zend_Db_Select::WHERE), true) . ' URL: ' . $currentUrl);
        }

        // Proceed with the original load method
        $result = $proceed($printQuery, $logQuery);

        // Log the final executed query
        $this->logger->info('Final Executed Query: ' . $subject->getSelect()->__toString() . ' URL: ' . $currentUrl);

        // Log the result count
        $this->logger->info('Result Count: ' . count($subject->getItems()) . ' URL: ' . $currentUrl);

        // Log the product IDs in the result
        $productIds = [];
        foreach ($subject->getItems() as $item) {
            $productIds[] = $item->getId();
        }
        $this->logger->info('Result Product IDs: ' . implode(', ', $productIds) . ' URL: ' . $currentUrl);

        return $result;
    }
}
