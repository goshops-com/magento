<?php
namespace Gopersonal\Magento\Block;

use Magento\Search\Api\SearchInterface;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Search\Model\SearchEngine as DefaultSearchEngine;

class CustomSearch implements SearchInterface {
    protected $httpClient;
    protected $scopeConfig;
    protected $resultJsonFactory;
    protected $defaultSearchEngine;

    public function __construct(
        \Magento\Framework\HTTP\ClientInterface $httpClient,
        ScopeConfigInterface $scopeConfig,
        JsonFactory $resultJsonFactory,
        DefaultSearchEngine $defaultSearchEngine // Injecting the default search engine
    ) {
        $this->httpClient = $httpClient;
        $this->scopeConfig = $scopeConfig;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->defaultSearchEngine = $defaultSearchEngine; // Store the default search engine
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
            // Fallback to default search engine functionality
            return $this->defaultSearchEngine->search($searchCriteria);
        }
    }
}

