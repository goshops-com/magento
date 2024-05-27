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
            $filters = $this->request->getParams(); // Get all request parameters (including filters)
            unset($filters['q']); // Remove the search query from filters if present

            $fixedProductIds = $this->getProductIds($searchQuery, $token, $filters); // Fetch product IDs dynamically

            if (!empty($fixedProductIds)) {
                // Reset the existing search query conditions
                $subject->getSelect()->reset(\Zend_Db_Select::WHERE);
                // Add the new condition with the fetched product IDs
                $subject->getSelect()->where('e.entity_id IN (?)', $fixedProductIds);
            }
        }

        return $proceed($printQuery, $logQuery); // Let the original load method proceed with the modified query
    }

    private function getProductIds($query, $token, $filters)
    {
        try {
            $clientId = $this->scopeConfig->getValue('gopersonal/general/client_id', ScopeInterface::SCOPE_STORE);
            $url = 'https://discover.gopersonal.ai/item/search?adapter=magento';

            if (strpos($clientId, 'D-') === 0) {
                $url = 'https://go-discover-dev.goshops.ai/item/search?adapter=magento';
            }

            $url .= '&query=' . urlencode($query);
            $url .= '&filter=' . urlencode(json_encode($filters)); // Add the filters as a JSON-encoded parameter

            $this->httpClient->addHeader("Authorization", "Bearer " . $token);
            $this->httpClient->get($url);
            $response = $this->httpClient->getBody();

            $productIds = json_decode($response, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($productIds)) {
                return $productIds;
            } else {
                $this->logger->error('Invalid JSON response: ' . json_last_error_msg());
                return [];
            }
        } catch (\Exception $e) {
            $this->logger->error('Error fetching product IDs: ' . $e->getMessage());
            return [];
        }
    }
}
