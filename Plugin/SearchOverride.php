<?php

namespace Gopersonal\Magento\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as SearchCollection;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

class SearchOverride
{
    protected $resourceConnection;
    protected $request;
    protected $scopeConfig;
    protected $cookieManager;
    protected $httpClient;
    protected $logger;
    protected $fetchedProductIds;

    public function __construct(
        ResourceConnection $resourceConnection,
        Http $request,
        ScopeConfigInterface $scopeConfig,
        CookieManagerInterface $cookieManager,
        Curl $httpClient,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->request = $request;
        $this->scopeConfig = $scopeConfig;
        $this->cookieManager = $cookieManager;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->fetchedProductIds = null;
    }

    public function aroundLoad(
        SearchCollection $subject,
        \Closure $proceed,
        $printQuery = false,
        $logQuery = false
    ) {
        try {
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
                }

                $this->logger->info('Final Executed Query in aroundLoad: ' . $subject->getSelect()->__toString());
                $this->logger->info('Result Count in aroundLoad: ' . count($subject->getItems()));
            }

            // Ensure the original load method is called
            return $proceed($printQuery, $logQuery);
        } catch (\Exception $e) {
            $this->logger->error('Error in aroundLoad: ' . $e->getMessage());
            // Ensure the original load method is called even in case of error
            return $proceed($printQuery, $logQuery);
        }
    }
}
