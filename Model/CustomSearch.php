<?php

namespace Gopersonal\Magento\Model;

use Magento\Search\Api\SearchInterface;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use Magento\Search\Model\SearchEngine;
use Magento\Framework\Api\Search\SearchResultFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\HTTP\ClientInterface;

class CustomSearch implements SearchInterface {

    protected $httpClient;
    protected $scopeConfig;
    protected $resultJsonFactory;
    protected $logger;
    protected $defaultSearchEngine;
    protected $searchResultFactory;
    protected $cookieManager; // Cookie Manager for retrieving the cookie


    public function __construct(
        ClientInterface $httpClient,
        ScopeConfigInterface $scopeConfig,
        JsonFactory $resultJsonFactory,
        LoggerInterface $logger,
        SearchEngine $defaultSearchEngine,
        SearchResultFactory $searchResultFactory,
        CustomerSession $customerSession
    ) {
        $this->httpClient = $httpClient;
        $this->scopeConfig = $scopeConfig;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->logger = $logger;
        $this->defaultSearchEngine = $defaultSearchEngine;
        $this->searchResultFactory = $searchResultFactory;
        $this->cookieManager = $cookieManager;
    }

    public function search(SearchCriteriaInterface $searchCriteria) {
        $isEnabled = $this->scopeConfig->getValue(
            'gopersonal/general/gopersonal_has_search',
            ScopeInterface::SCOPE_STORE
        );

        if ($isEnabled == 'YES') {
            $this->logger->info('CustomSearch: External search is enabled');

            $token = $this->cookieManager->getCookie('gopersonal_jwt');

            if (!$token) {
                $this->logger->info('No API token found in session.');
                $searchResult = $this->searchResultFactory->create();
                $searchResult->setSearchCriteria($searchCriteria);
        
                // Create an instance of DataObject with product data
                $itemData = [
                    'id' => 1556
                ];
                $item = new \Magento\Framework\DataObject($itemData);
        
                // Set the items and total count
                $searchResult->setItems([$item]);
                $searchResult->setTotalCount(1); // Set total count to 1 as we're returning 1 product
        
                return $searchResult;
            }

            $clientId = $this->scopeConfig->getValue('gopersonal/general/client_id', ScopeInterface::SCOPE_STORE);
            $url = 'https://discover.gopersonal.ai/item/search?adapter=magento';

            if (strpos($clientId, 'D-') === 0) {
                $url = 'https://go-discover-dev.goshops.ai/search?adapter=magento';
            }

            // Get the search query and filters from the search criteria
            $query = $searchCriteria->getQuery();
            $filters = $searchCriteria->getFilterGroups();

            // Add the query and filters to the URL
            $url .= '&query=' . urlencode($query);
            foreach ($filters as $filterGroup) {
                foreach ($filterGroup->getFilters() as $filter) {
                    $url .= '&' . $filter->getField() . '=' . urlencode($filter->getValue());
                }
            }

            // Set the authorization header with the token
            $this->httpClient->addHeader("Authorization", "Bearer " . $token);

            // Execute the API request
            $this->httpClient->get($url);
            $response = $this->httpClient->getBody();

            // Parse the response and extract the product IDs
            $productIds = json_decode($response);

            // Create a search result object
            $searchResult = $this->searchResultFactory->create();
            $searchResult->setSearchCriteria($searchCriteria);

            // Set the items and total count based on the retrieved product IDs
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
        } else {
            $this->logger->info('CustomSearch: Fallback to default search engine');
            
            // Convert SearchCriteria to RequestInterface
            $searchRequest = $this->searchRequestBuilder->setSearchCriteria($searchCriteria)->create();
            return $this->defaultSearchEngine->search($searchRequest);
        }
    }
}