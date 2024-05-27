<?php

namespace Gopersonal\Magento\Plugin\Search;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Catalog\Model\Product\Visibility;

class ProductCollectionPlugin
{
    protected $collectionFactory;
    protected $productVisibility;

    public function __construct(
        CollectionFactory $collectionFactory,
        Visibility $productVisibility
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->productVisibility = $productVisibility;
    }

    public function aroundGetList(
        \Magento\Catalog\Api\ProductRepositoryInterface $subject,
        callable $proceed,
        SearchCriteriaInterface $searchCriteria
    ) {
        // List of product IDs to search
        $productIds = [1556, 1234, 5678]; // Replace with your dynamic IDs

        $collection = $this->collectionFactory->create();
        $collection->addIdFilter($productIds);
        $collection->setVisibility($this->productVisibility->getVisibleInCatalogIds());

        // Convert collection to search results
        $searchResults = $subject->getSearchResultsFactory()->create();
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());
        $searchResults->setSearchCriteria($searchCriteria);

        return $searchResults;
    }
}
