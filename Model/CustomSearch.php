<?php
namespace Gopersonal\Magento\Model;

use Magento\Search\Api\SearchInterface;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use Magento\Framework\Api\Search\SearchResultFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Search\Api\SearchInterface as DefaultSearchInterface;

class CustomSearch implements SearchInterface {
    protected $httpClient;
    protected $scopeConfig;
    protected $resultJsonFactory;
    protected $defaultSearchEngine; // Ensure this variable is used appropriately
    protected $searchResultFactory;

    public function __construct(
        \Magento\Framework\HTTP\ClientInterface $httpClient,
        ScopeConfigInterface $scopeConfig,
        JsonFactory $resultJsonFactory,
        DefaultSearchInterface $defaultSearchEngine, // This should implement SearchInterface
        SearchResultFactory $searchResultFactory
    ) {
        $this->httpClient = $httpClient;
        $this->scopeConfig = $scopeConfig;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->defaultSearchEngine = $defaultSearchEngine;
        $this->searchResultFactory = $searchResultFactory;
    }

    public function search(SearchCriteriaInterface $searchCriteria) {
        $isEnabled = $this->scopeConfig->getValue(
            'gopersonal/general/gopersonal_has_search',
            ScopeInterface::SCOPE_STORE
        );

        if ($isEnabled == 'YES') {
            // Create a JSON response with hardcoded product data
            $result = $this->resultJsonFactory->create();
            $productData = [
                'items' => [
                    [
                        'product_id' => 45,
                        'name' => 'Sample Product',
                        'price' => 99.99,
                        'description' => 'This is a sample product from hardcoded search result.'
                    ]
                ],
                'total_count' => 1,
                'search_criteria' => $searchCriteria,
                'aggregations' => null
            ];
            return $result->setData($productData);
        } else {
            // Fallback to default search engine functionality
            return $this->defaultSearchEngine->search($searchCriteria);
        }
    }
}
