<?php

namespace Gopersonal\Magento\Model;

use Psr\Log\LoggerInterface;
use Magento\Framework\Api\Search\SearchResultFactory;
use Magento\Search\Model\SearchEngine;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;

class CustomSearch
{
    protected $searchEngine;
    protected $searchResultFactory;
    protected $productCollectionFactory;
    protected $logger;

    public function __construct(
        SearchEngine $searchEngine,
        SearchResultFactory $searchResultFactory,
        ProductCollectionFactory $productCollectionFactory,
        LoggerInterface $logger
    ) {
        $this->searchEngine = $searchEngine;
        $this->searchResultFactory = $searchResultFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->logger = $logger;
    }

    public function search(SearchCriteriaInterface $searchCriteria)
    {
        $this->logger->info('CustomSearch: search method called');
        
        // Create a hardcoded search result with product ID 1556
        $searchResult = $this->searchResultFactory->create();
        $this->logger->info('CustomSearch: SearchResultFactory created');
        
        $searchResult->setSearchCriteria($searchCriteria);
        $this->logger->info('CustomSearch: SearchCriteria set');

        $productCollection = $this->productCollectionFactory->create();
        $this->logger->info('CustomSearch: ProductCollectionFactory created');
        
        $productCollection->addIdFilter([1556]);
        $this->logger->info('CustomSearch: ProductCollection filtered with ID 1556');
        
        $searchResult->setItems($productCollection->getItems());
        $this->logger->info('CustomSearch: Items set in SearchResult');

        $searchResult->setTotalCount($productCollection->getSize());
        $this->logger->info('CustomSearch: TotalCount set in SearchResult');

        return $searchResult;
    }

    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        $this->logger->info('CustomSearch: getList method called');
        
        // Call the search method for consistency
        return $this->search($searchCriteria);
    }
}
