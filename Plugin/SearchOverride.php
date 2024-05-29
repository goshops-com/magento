<?php

namespace Gopersonal\Magento\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as SearchCollection;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Psr\Log\LoggerInterface;

class SearchOverride
{
    protected $request;
    protected $scopeConfig;
    protected $cookieManager;
    protected $logger;
    protected $fetchedProductIds;

    public function __construct(
        Http $request,
        ScopeConfigInterface $scopeConfig,
        CookieManagerInterface $cookieManager,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->scopeConfig = $scopeConfig;
        $this->cookieManager = $cookieManager;
        $this->logger = $logger;
        $this->fetchedProductIds = null;
    }

    public function aroundLoad(
        SearchCollection $subject,
        \Closure $proceed,
        $printQuery = false,
        $logQuery = false
    ) {
        $searchQuery = $this->request->getParam('q');
        $isEnabled = $this->scopeConfig->getValue(
            'gopersonal/general/gopersonal_has_search',
            ScopeInterface::SCOPE_STORE
        );
        $token = $this->cookieManager->getCookie('gopersonal_jwt');

        if ($searchQuery && $isEnabled) {
            $filters = $this->request->getParams();
            unset($filters['q']);

            if ($this->fetchedProductIds === null) {
                $this->fetchedProductIds = [1556]; // Static ID for testing
            }

            if (!empty($this->fetchedProductIds)) {
                $subject->getSelect()->reset(\Zend_Db_Select::WHERE);
                $subject->getSelect()->where('e.entity_id IN (?)', $this->fetchedProductIds);

                $this->logger->info('Custom search executed. Final Executed Query: ' . $subject->getSelect()->__toString());
                $this->logger->info('Result Count: ' . count($subject->getItems()));

                // Prevent default search by skipping $proceed call
                return $subject;
            }
        }

        // Call the original load method if custom conditions are not met
        return $proceed($printQuery, $logQuery);
    }
}
