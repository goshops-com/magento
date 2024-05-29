<?php

namespace Gopersonal\Magento\Plugin;

use Magento\CatalogSearch\Model\Layer;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as SearchCollection;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

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

        // Remove 'q' parameter immediately
        $params = $this->request->getParams();
        unset($params['q']);
        $this->request->setParams($params);
    }

    protected function getCurrentUrl()
    {
        return $this->request->getUriString();
    }

    // ... (hasDefaultSearchConditions method is the same as before)

    public function aroundLoad(SearchCollection $subject, \Closure $proceed, $printQuery = false, $logQuery = false)
    {
        $currentUrl = $this->getCurrentUrl();
        $this->logger->info("aroundLoad called on URL: " . $currentUrl); // Log URL before execution
        
        if ($this->request->getParam(self::FLAG_KEY)) {
            $this->logger->info('Plugin Execution Skipped (Already Executed) URL: ' . $currentUrl);
            return $proceed($printQuery, $logQuery);
        }

        $this->request->setParam(self::FLAG_KEY, true);
        $fixedProductIds = [1556]; // Or fetch dynamically 

        // Check if Magento's default search is active
        $isMagentoSearch = !empty($this->request->getParam('q')) || $this->hasDefaultSearchConditions($subject);
        
        if ($isMagentoSearch) {
            // Reset and apply your custom filters (moved from _renderFiltersBefore)
            $subject->clear();
            $subject->getSelect()->reset(Zend_Db_Select::WHERE);
            $subject->getSelect()->where('e.entity_id IN (?)', $fixedProductIds);
            // ... (rest of your query modifications, if any) ...
        } 

        $result = $proceed($printQuery, $logQuery);

        // Log the final executed query
        $this->logger->info('Final Executed Query (aroundLoad): ' . $subject->getSelect()->__toString() . ' URL: ' . $currentUrl);

        return $result; 
    }

    public function afterLoad(SearchCollection $subject)
    {
        $currentUrl = $this->getCurrentUrl();
        $this->logger->info("afterLoad called on URL: " . $currentUrl);

        // Check if this is a search context and the flag is not set (to avoid multiple executions)
        $isMagentoSearch = !empty($this->request->getParam('q')) || $this->hasDefaultSearchConditions($subject);
        if ($isMagentoSearch && !$this->request->getParam(self::FLAG_KEY)) {

            $fixedProductIds = [1556]; // Or fetch dynamically 

            // Clear existing items and add only filtered products
            $subject->clear();
            $items = [];
            foreach ($fixedProductIds as $productId) {
                $item = $subject->getNewEmptyItem();
                $item->setData('entity_id', $productId);
                $items[] = $item;
            }
            $subject->setItems($items);
        }

        // Set the flag to prevent multiple executions
        $this->request->setParam(self::FLAG_KEY, true);

        // Log the final executed query AFTER your modifications
        $this->logger->info('Final Executed Query (afterLoad): ' . $subject->getSelect()->__toString() . " URL: " . $currentUrl);
        return $subject;
    }
}