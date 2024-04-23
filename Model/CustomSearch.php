<?php
namespace Gopersonal\Magento\Model;

use Magento\Search\Api\SearchInterface;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;

class CustomSearch implements SearchInterface {
    protected $httpClient;
    protected $scopeConfig;
    protected $resultJsonFactory;
    protected $searchHandler;
    protected $logger; // Add logger

    public function __construct(
        \Magento\Framework\HTTP\ClientInterface $httpClient,
        ScopeConfigInterface $scopeConfig,
        JsonFactory $resultJsonFactory,
        SearchHandler $searchHandler,
        LoggerInterface $logger // Inject Logger
    ) {
        $this->httpClient = $httpClient;
        $this->scopeConfig = $scopeConfig;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->searchHandler = $searchHandler;
        $this->logger = $logger; // Initialize logger
    }

    public function search(SearchCriteriaInterface $searchCriteria) {
        $this->logger->info('CustomSearch: Start search process'); // Log start of search
        $isEnabled = $this->scopeConfig->getValue(
            'gopersonal/general/gopersonal_has_search',
            ScopeInterface::SCOPE_STORE
        );

        // $this->logger->critical('Testing if logging works');
        $this->logger->critical('CustomSearch: isEnabled value', ['isEnabled' => $isEnabled]); // Log configuration value

        if ($isEnabled == 'YES') {
            $this->logger->info('CustomSearch: External search is enabled'); // Log that external search is enabled
            $result = $this->resultJsonFactory->create();
            $productData = [
                'product_id' => 45,
                'name' => 'Sample Product',
                'price' => 99.99,
                'description' => 'This is a sample product from hardcoded search result.'
            ];
            $this->logger->info('CustomSearch: Hardcoded product data returned', $productData); // Log product data
            return $result->setData($productData);
        } else {
            $this->logger->info('CustomSearch: Fallback to default search'); // Log fallback scenario
            // Use the delegate to handle the default search logic
            return $this->searchHandler->search($searchCriteria);
        }
    }
}
