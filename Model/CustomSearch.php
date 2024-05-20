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
use Magento\CatalogSearch\Model\Search\Search; // Import the real search class

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
    protected $realSearchInterface;

    public function __construct(
        ClientInterface $httpClient,
        ScopeConfigInterface $scopeConfig,
        JsonFactory $resultJsonFactory,
        LoggerInterface $logger,
        SearchEngine $defaultSearchEngine,
        SearchResultFactory $searchResultFactory,
        CookieManagerInterface $cookieManager,
        CustomerSession $customerSession,
        SearchRequestBuilder $searchRequestBuilder,
        Search $realSearchInterface
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

    private function isCategoryPage(SearchCriteriaInterface $searchCriteria) {
        foreach ($searchCriteria->getFilterGroups() as $group) {
            foreach ($group->getFilters() as $filter) {
                if ($filter->getField() === 'category_ids') {
                    return true;
                }
            }
        }
        return false;
    }

    private function buildRequest(SearchCriteriaInterface $searchCriteria)
    {
        $requestName = 'quick_search_container'; // You may need to adjust this based on your configuration

        $this->searchRequestBuilder->setRequestName($requestName);
        $this->searchRequestBuilder->bindRequestValue('search_term', $searchCriteria->getRequestValue('search_term'));

        return $this->searchRequestBuilder->create();
    }

    public function search(SearchCriteriaInterface $searchCriteria) {
        $isEnabled = $this->scopeConfig->getValue(
            'gopersonal/general/gopersonal_has_search',
            ScopeInterface::SCOPE_STORE
        );

        $searchTerm = $this->getQueryFromSearchCriteria($searchCriteria);

        // Check if custom search should be disabled
        if ($isEnabled != 'YES' || empty($searchTerm)) {
            $this->logger->info('CustomSearch: Fallback to default Elasticsearch search engine.');
            
            // Use the real Elasticsearch search implementation
            $request = $this->buildRequest($searchCriteria);
            return $this->realSearchInterface->search($request); 
        }

        if ($isEnabled == 'YES') {
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
    }
}
