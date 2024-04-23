<?php
namespace Gopersonal\Magento\Model;

use Magento\Search\Api\SearchInterface;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Controller\Result\JsonFactory;

class CustomSearch implements SearchInterface {
    protected $httpClient;
    protected $scopeConfig;
    protected $resultJsonFactory;
    protected $searchHandler; // Delegate for handling search

    public function __construct(
        \Magento\Framework\HTTP\ClientInterface $httpClient,
        ScopeConfigInterface $scopeConfig,
        JsonFactory $resultJsonFactory,
        SearchHandler $searchHandler // This is a custom class handling the actual search logic
    ) {
        $this->httpClient = $httpClient;
        $this->scopeConfig = $scopeConfig;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->searchHandler = $searchHandler;
    }

    public function search(SearchCriteriaInterface $searchCriteria) {
        $isEnabled = $this->scopeConfig->getValue(
            'gopersonal/general/gopersonal_has_search',
            ScopeInterface::SCOPE_STORE
        );

        if ($isEnabled == 'YES') {
            $result = $this->resultJsonFactory->create();
            $productData = [
                'product_id' => 45,
                'name' => 'Sample Product',
                'price' => 99.99,
                'description' => 'This is a sample product from hardcoded search result.'
            ];
            return $result->setData($productData);
        } else {
            // Use the delegate to handle the default search logic
            return $this->searchHandler->search($searchCriteria);
        }
    }
}
