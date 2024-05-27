<?php

namespace Gopersonal\Magento\Model;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Api\SearchResults;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Psr\Log\LoggerInterface;

class CustomSearch
{
    protected $collectionFactory;
    protected $productRepository;
    protected $searchResultsFactory;
    protected $logger;

    public function __construct(
        CollectionFactory $collectionFactory,
        ProductRepositoryInterface $productRepository,
        \Magento\Framework\Api\Search\SearchResultFactory $searchResultsFactory,
        LoggerInterface $logger
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->productRepository = $productRepository;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->logger = $logger;
    }

    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        $this->logger->info('CustomSearch getList called.');

        // List of product IDs to search
        $productIds = [1556]; // Replace with your dynamic IDs

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('entity_id', ['in' => $productIds]);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());
        $searchResults->setSearchCriteria($searchCriteria);

        return $searchResults;
    }
}
