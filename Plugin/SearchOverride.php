<?php

namespace Gopersonal\Magento\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as SearchCollection;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class SearchOverride
{
    protected $resourceConnection;
    protected $request;
    protected $scopeConfig;
    protected $logger;

    public function __construct(
        ResourceConnection $resourceConnection,
        Http $request,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->request = $request;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    public function aroundLoad(
        SearchCollection $subject,
        \Closure $proceed,
        $printQuery = false,
        $logQuery = false
    ) {
        $searchQuery = $this->request->getParam('q'); // Get the search query parameter
        $isEnabled = $this->scopeConfig->getValue(
            'gopersonal/general/gopersonal_has_search',
            ScopeInterface::SCOPE_STORE
        );

        $this->logger->info('Search query: ' . $searchQuery);
        $this->logger->info('Plugin enabled: ' . ($isEnabled === 'YES' ? 'YES' : 'NO'));

        if ($searchQuery && $isEnabled === 'YES') {
            $fixedProductIds = [1556]; // Your array of fixed product IDs (or fetch dynamically)
            $this->logger->info('Fixed product IDs: ' . implode(',', $fixedProductIds));

            if (!empty($fixedProductIds)) {
                $select = $subject->getSelect();
                $this->logger->info('Original query: ' . $select->__toString());

                // Modify the query to only include the fixed product IDs
                $select->reset(\Zend_Db_Select::WHERE);
                $select->reset(\Zend_Db_Select::ORDER);
                $select->reset(\Zend_Db_Select::LIMIT_COUNT);
                $select->reset(\Zend_Db_Select::LIMIT_OFFSET);

                $select->where('e.entity_id IN (?)', $fixedProductIds);
                
                $this->logger->info('Modified query: ' . $select->__toString());
            }
        }

        return $proceed($printQuery, $logQuery); // Let the original load method proceed with the modified query
    }
}
