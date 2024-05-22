<?php

namespace Gopersonal\Magento\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\Product\Visibility;
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
use Magento\Framework\Api\Search\DocumentFactory;
use Magento\Framework\Api\Search\Document;
use Magento\Framework\App\RequestInterface as HttpRequestInterface;
use Magento\CatalogInventory\Api\StockStateInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;

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
    protected $documentFactory;
    protected $httpRequest;
    protected $productRepository;
    protected $productCollectionFactory;
    protected $productVisibility;
    protected $stockState;
    protected $stockRegistry;

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
        DocumentFactory $documentFactory,
        HttpRequestInterface $httpRequest,
        ProductRepositoryInterface $productRepository,
        ProductCollectionFactory $productCollectionFactory,
        Visibility $productVisibility,
        StockStateInterface $stockState,
        StockRegistryInterface $stockRegistry
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
        $this->documentFactory = $documentFactory;
        $this->httpRequest = $httpRequest;
        $this->productRepository = $productRepository;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productVisibility = $productVisibility;
        $this->stockState = $stockState;
        $this->stockRegistry = $stockRegistry;
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

    private function buildRequest(SearchCriteriaInterface $searchCriteria) {
        $requestName = 'quick_search_container'; // Adjust this if needed
        $this->searchRequestBuilder->setRequestName($requestName);
        foreach ($searchCriteria->getFilterGroups() as $group) {
            foreach ($group->getFilters() as $filter) {
                $this->searchRequestBuilder->bind($filter->getField(), $filter->getValue());
            }
        }
        return $this->searchRequestBuilder->create();
    }

    private function parseProductIds($idsString) {
        $idsArray = explode(',', $idsString);
        return array_map('trim', $idsArray);
    }

    public function search(SearchCriteriaInterface $searchCriteria) {
        $isEnabled = $this->scopeConfig->getValue(
            'gopersonal/general/gopersonal_has_search',
            ScopeInterface::SCOPE_STORE
        );

        $searchTerm = $this->getQueryFromSearchCriteria($searchCriteria);

        // Check for custom query parameter in the URL
        $customProductIdsString = $this->httpRequest->getParam('gs_product_ids');
        if ($customProductIdsString) {
            $productIds = $this->parseProductIds($customProductIdsString);

            // Validate product IDs
            $validateStock = true; // Set this to false to disable stock validation
            $validProductIds = $this->validateProductIds($productIds, $validateStock);

            $searchResult = $this->searchResultFactory->create();
            $searchResult->setSearchCriteria($searchCriteria);

            $items = [];
            foreach ($validProductIds as $productId) {
                $itemData = [
                    'id' => $productId
                ];
                $items[] = new \Magento\Framework\DataObject($itemData);
            }
            $searchResult->setItems($items);
            $searchResult->setTotalCount(count($items));

            return $searchResult;
        }

        $token = $this->cookieManager->getCookie('gopersonal_jwt');

        // Check if custom search should be disabled
        if ($isEnabled != 'YES' || empty($searchTerm) || !$token) {
            // Convert SearchCriteriaInterface to RequestInterface
            $request = $this->buildRequest($searchCriteria);

            // Pass the request to the default search engine
            $defaultResponse = $this->defaultSearchEngine->search($request);

            // Convert default response to SearchResultInterface
            return $this->convertToSearchResult($defaultResponse, $searchCriteria);
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

            if (json_last_error() !== JSON_ERROR_NONE) {
                // Fallback to default search engine if response is not valid JSON
                $this->logger->error('CustomSearch: Invalid JSON response from external search, falling back to default search engine.');
                $request = $this->buildRequest($searchCriteria);
                $defaultResponse = $this->defaultSearchEngine->search($request);
                return $this->convertToSearchResult($defaultResponse, $searchCriteria);
            }

            // Validate product IDs
            $validateStock = true; // Set this to false to disable stock validation
            $validProductIds = $this->validateProductIds($productIds, $validateStock);

            $searchResult = $this->searchResultFactory->create();
            $searchResult->setSearchCriteria($searchCriteria);

            $items = [];
            foreach ($validProductIds as $productId) {
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

    private function validateProductIds($productIds, $validateStock = true) {
        $collection = $this->productCollectionFactory->create()
            ->addAttributeToSelect(['entity_id', 'status', 'visibility'])
            ->addFieldToFilter('entity_id', ['in' => $productIds])
            ->addFieldToFilter('status', ['eq' => \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED])
            ->addFieldToFilter('visibility', ['neq' => Visibility::VISIBILITY_NOT_VISIBLE]);

        if ($validateStock) {
            $collection->joinField(
                'qty',
                'cataloginventory/stock_item',
                'qty',
                'product_id=entity_id',
                '{{table}}.stock_id=1',
                'left'
            )->joinField(
                'is_in_stock',
                'cataloginventory/stock_item',
                'is_in_stock',
                'product_id=entity_id',
                '{{table}}.stock_id=1',
                'left'
            )->addFieldToFilter('is_in_stock', ['eq' => 1])
             ->addFieldToFilter('qty', ['gt' => 0]);
        }

        $validProductIds = $collection->getAllIds();

        return $validProductIds;
    }

    private function convertToSearchResult($defaultResponse, SearchCriteriaInterface $searchCriteria) {
        $searchResult = $this->searchResultFactory->create();
        $searchResult->setSearchCriteria($searchCriteria);

        $items = [];
        foreach ($defaultResponse->getIterator() as $document) {
            $itemData = [
                'id' => $document->getId(),
                // Add other fields if needed
            ];
            $items[] = new \Magento\Framework\DataObject($itemData);
        }
        $searchResult->setItems($items);
        $searchResult->setTotalCount($defaultResponse->count());

        return $searchResult;
    }
}
