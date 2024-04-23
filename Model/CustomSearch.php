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

class CustomSearch implements SearchInterface {
    protected $httpClient;
    protected $scopeConfig;
    protected $resultJsonFactory;
    protected $logger;
    protected $defaultSearchEngine;
    protected $searchResultFactory;

    public function __construct(
        \Magento\Framework\HTTP\ClientInterface $httpClient,
        ScopeConfigInterface $scopeConfig,
        JsonFactory $resultJsonFactory,
        LoggerInterface $logger,
        SearchEngine $defaultSearchEngine,
        SearchResultFactory $searchResultFactory // Inject SearchResultFactory
    ) {
        $this->httpClient = $httpClient;
        $this->scopeConfig = $scopeConfig;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->logger = $logger;
        $this->defaultSearchEngine = $defaultSearchEngine;
        $this->searchResultFactory = $searchResultFactory;
    }

    public function search(SearchCriteriaInterface $searchCriteria) {
        $isEnabled = $this->scopeConfig->getValue(
            'gopersonal/general/gopersonal_has_search',
            ScopeInterface::SCOPE_STORE
        );
    
        if ($isEnabled == 'YES') {
            $this->logger->info('CustomSearch: External search is enabled');
    
            // Create a search result object
            $searchResult = $this->searchResultFactory->create();
            $searchResult->setSearchCriteria($searchCriteria);
    
            // Create an instance of DataObject with product data
            $itemData = [
                'id' => 1556,
                'name' => 'Sample Product',
                'price' => 99.99,
                'description' => 'This is a sample product from a hardcoded search result.'
            ];
            $item = new \Magento\Framework\DataObject($itemData);
    
            // Set the items and total count
            $searchResult->setItems([$item]);
            $searchResult->setTotalCount(1); // Set total count to 1 as we're returning 1 product
    
            return $searchResult;
        } else {
            $this->logger->info('CustomSearch: Fallback to default search engine');
            return $this->defaultSearchEngine->search($searchCriteria);
        }
    }
}
