<?php

namespace Gopersonal\Magento\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as SearchCollection;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;

class SearchOverride
{
    protected $resourceConnection;
    protected $request;
    protected $scopeConfig;
    protected $cookieManager;

    public function __construct(
        ResourceConnection $resourceConnection,
        Http $request,
        ScopeConfigInterface $scopeConfig,
        CookieManagerInterface $cookieManager
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->request = $request;
        $this->scopeConfig = $scopeConfig;
        $this->cookieManager = $cookieManager;
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
        $token = $this->cookieManager->getCookie('gopersonal_jwt'); // Get the token from the cookie

        if ($searchQuery && $isEnabled === 'YES' && $token !== null) {
            $fixedProductIds = [123, 456, 789]; // Your array of fixed product IDs (or fetch dynamically)

            if (!empty($fixedProductIds)) {
                $subject->getSelect()->reset(\Zend_Db_Select::WHERE);
                $subject->getSelect()->where('e.entity_id IN (?)', $fixedProductIds);
            }
        }

        return $proceed($printQuery, $logQuery); // Let the original load method proceed with the modified query
    }
}
