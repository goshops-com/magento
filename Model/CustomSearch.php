<?php

namespace Gopersonal\Magento\Model;

use Magento\Search\Api\SearchInterface;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use Magento\Search\Model\SearchEngine;
use Magento\Framework\Api\Search\SearchResultFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Search\Request\Builder as SearchRequestBuilder;
use Magento\Framework\Search\RequestInterface;

class CustomSearch implements SearchInterface {

    protected $httpClient;
    protected $scopeConfig;
    protected $resultJsonFactory;
    protected $logger;
    protected $defaultSearchEngine;
    protected $searchResultFactory;
    protected $cookieManager;
    protected $searchRequestBuilder;
    protected $customerSession;

    public function __construct(
        ClientInterface $httpClient,
        ScopeConfigInterface $scopeConfig,
        JsonFactory $resultJsonFactory,
        LoggerInterface $logger,
        SearchEngine $defaultSearchEngine,
        SearchResultFactory $searchResultFactory,
        CookieManagerInterface $cookieManager,
        CustomerSession $customerSession,
        SearchRequestBuilder $searchRequestBuilder
    ) {
        $this->httpClient = $httpClient;
        $this->scopeConfig = $scopeConfig;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->logger = $logger;
        $this->defaultSearchEngine = $defaultSearchEngine;
        $this->searchResultFactory = $searchResultFactory;
        $this->cookieManager = $cookieManager;
        $this->customerSession = $customerSession;
        $this->searchRequestBuilder = $searchRequestBuilder;
    }

    private function getQueryFromSearchCriteria(SearchCriteriaInterface $searchCriteria) {
        foreach ($searchCriteria->getFilterGroups() as $group) {
            foreach ($group->getFilters() as $filter) {
                if ($filter->getField() === 'search_term') {
                    return $filter->getValue();
                }
            }
        }
        return null;
    }

    public function search(SearchCriteriaInterface $searchCriteria) {
        $isEnabled = $this->scopeConfig->getValue(
            'gopersonal/general/gopersonal_has_search',
            ScopeInterface::SCOPE_STORE
        );

        $searchTerm = $this->getQueryFromSearchCriteria($searchCriteria);

        if ($isEnabled != 'YES') {
            $this->logger->info('CustomSearch: Fallback to default search engine (disabled)');
            return $this->defaultSearch($searchCriteria);
        }

        if ($isEnabled == 'YES' && empty($searchTerm)) {
            $this->logger->info('CustomSearch: Fallback to default search engine (no search term)');
            return $this->defaultSearch($searchCriteria);
        }

        $this->logger->info('CustomSearch: External search is enabled');

        $filtersJson = [];

        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            foreach ($filterGroup->getFilters() as $filter) {
                $field = $filter->getField();
                $value = $filter->getValue();

                if (!isset($filtersJson[$field])) {
                    $filtersJson[$field] = [];
                }

                $filtersJson[$field][] = $value;
            }
        }

        $token = $this->cookieManager->getCookie('gopersonal_jwt');

        if (!$token) {
            $this->logger->info('No API token found in session.');
            $searchResult = $this->searchResultFactory->create();
            $searchResult->setSearchCriteria($searchCriteria);

            $itemData = [
                'id' => 1556
            ];
            $item = new \Magento\Framework\DataObject($itemData);

            $searchResult->setItems([$item]);
            $searchResult->setTotalCount(1);

            return $searchResult;
        }

        $clientId = $this->scopeConfig->getValue('gopersonal/general/client_id', ScopeInterface::SCOPE_STORE);
        $url = 'https://discover.gopersonal.ai/item/search?adapter=magento';

        if (strpos($clientId, 'D-') === 0) {
            $url = 'https://go-discover-dev.goshops.ai/item/search?adapter=magento';
        }

        $query = $searchTerm;

        $queryParam = $query ? '&query=' . urlencode($query) : '';
        $filtersParam = !empty($filtersJson) ? '&filters=' . urlencode(json_encode($filtersJson)) : '';

        $url .= $queryParam . $filtersParam;

        $this->httpClient->addHeader("Authorization", "Bearer " . $token);

        $this->httpClient->get($url);
        $response = $this->httpClient->getBody();

        $productIds = json_decode($response);

        $searchResult = $this->searchResultFactory->create();
        $searchResult->setSearchCriteria($searchCriteria);

        $items = [];
        foreach ($productIds as $productId) {
            $itemData = [
                'id' => $productId
            ];
            $items[] = new \Magento\Framework\DataObject($itemData);
        }
        $searchResult->setItems($items);
        $searchResult->setTotalCount(count($items));

        return $searchResult;
    }

    private function defaultSearch(SearchCriteriaInterface $searchCriteria) {
        $this->logger->info('Executing default Magento search (simplified)');
        
        // Log the search criteria
        $this->logger->info('Search Criteria: ' . print_r($searchCriteria->toArray(), true));
    
        // Build the search request
        $requestBuilder = $this->searchRequestBuilder->create();
        $request = $requestBuilder->build(); // Create a bare-bones request
    
        // Log the request object for debugging
        $this->logger->info('Search Request: ' . print_r($request->toArray(), true));
    
        return $this->defaultSearchEngine->search($request);
    }

}
