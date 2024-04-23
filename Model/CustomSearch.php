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

            // Simulate a search result for demonstration
            $searchResult = $this->searchResultFactory->create();
            $searchResult->setSearchCriteria($searchCriteria);
            $searchResult->setItems([]); // Your actual search results here
            $searchResult->setTotalCount(0); // Your actual count here
            return $searchResult;
        } else {
            $this->logger->info('CustomSearch: Fallback to default search engine');
            return $this->defaultSearchEngine->search($searchCriteria);
        }
    }
}
