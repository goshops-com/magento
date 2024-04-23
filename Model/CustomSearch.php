<?php
namespace Gopersonal\Magento\Model;

use Magento\Search\Api\SearchInterface;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use Magento\Search\Model\SearchEngine; // Assuming this is the default search class

class CustomSearch implements SearchInterface {
    protected $httpClient;
    protected $scopeConfig;
    protected $resultJsonFactory;
    protected $logger;
    protected $defaultSearchEngine; // Default search engine service

    public function __construct(
        \Magento\Framework\HTTP\ClientInterface $httpClient,
        ScopeConfigInterface $scopeConfig,
        JsonFactory $resultJsonFactory,
        LoggerInterface $logger,
        SearchEngine $defaultSearchEngine // Inject the default search engine
    ) {
        $this->httpClient = $httpClient;
        $this->scopeConfig = $scopeConfig;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->logger = $logger;
        $this->defaultSearchEngine = $defaultSearchEngine; // Initialize the default search engine
    }

    public function search(SearchCriteriaInterface $searchCriteria) {
        $isEnabled = $this->scopeConfig->getValue(
            'gopersonal/general/gopersonal_has_search',
            ScopeInterface::SCOPE_STORE
        );

        $this->logger->info('CustomSearch: Start search process');

        if ($isEnabled == 'YES') {
            $result = $this->resultJsonFactory->create();
            $productData = [
                'product_id' => 45,
                'name' => 'Sample Product',
                'price' => 99.99,
                'description' => 'This is a sample product from hardcoded search result.'
            ];
            $this->logger->info('CustomSearch: Returning hardcoded product data');
            return $result->setData($productData);
        } else {
            // Fallback to default search engine functionality
            $this->logger->info('CustomSearch: Fallback to default search engine');
            return $this->defaultSearchEngine->search($searchCriteria);
        }
    }
}
