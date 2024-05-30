<?php

namespace Gopersonal\Magento\Model;

use Magento\Framework\Api\Search\SearchResultFactory;
use Magento\Search\Model\SearchEngine;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;

class CustomSearch
{
    protected $searchEngine;
    protected $searchResultFactory;
    protected $productCollectionFactory;

    public function __construct(
        SearchEngine $searchEngine,
        SearchResultFactory $searchResultFactory,
        ProductCollectionFactory $productCollectionFactory
    ) {
        $this->searchEngine = $searchEngine;
        $this->searchResultFactory = $searchResultFactory;
        $this->productCollectionFactory = $productCollectionFactory;
    }

    public function search(SearchCriteriaInterface $searchCriteria)
    {
        // Create a hardcoded search result with product ID 1556
        $searchResult = $this->searchResultFactory->create();
        $productCollection = $this->productCollectionFactory->create();
        $productCollection->addIdFilter([1556]);
        
        $searchResult->setItems($productCollection->getItems());
        $searchResult->setTotalCount($productCollection->getSize());

        return $searchResult;
    }

    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        // Call the search method for consistency
        return $this->search($searchCriteria);
    }
}
