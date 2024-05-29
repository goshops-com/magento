<?php

namespace Gopersonal\Magento\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as SearchCollection;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class SearchOverride
{
    protected $resourceConnection;
    protected $request;
    protected $scopeConfig;

    public function __construct(
        ResourceConnection $resourceConnection,
        Http $request,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->request = $request;
        $this->scopeConfig = $scopeConfig;
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

        if ($searchQuery && $isEnabled === 'YES') {
            $fixedProductIds = [1556]; // Your array of fixed product IDs (or fetch dynamically)

            if (!empty($fixedProductIds)) {
                $subject->getSelect()->reset(\Zend_Db_Select::WHERE);
                $subject->getSelect()->where('e.entity_id IN (?)', $fixedProductIds);
            }
        }

        return $proceed($printQuery, $logQuery); // Let the original load method proceed with the modified query
    }
}
