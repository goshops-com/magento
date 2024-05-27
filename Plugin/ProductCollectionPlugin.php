<?php

namespace Gopersonal\Magento\Plugin;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\Filter;
use Psr\Log\LoggerInterface;

class ProductCollectionPlugin
{
    protected $collectionFactory;
    protected $productVisibility;
    protected $logger;

    public function __construct(
        CollectionFactory $collectionFactory,
        Visibility $productVisibility,
        LoggerInterface $logger
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->productVisibility = $productVisibility;
        $this->logger = $logger;
    }

    public function aroundGetList(
        \Magento\Catalog\Api\ProductRepositoryInterface $subject,
        callable $proceed,
        SearchCriteriaInterface $searchCriteria
    ) {
        $this->logger->info('ProductCollectionPlugin aroundGetList called.');

        // List of product IDs to search
        $productIds = [1556]; // Replace with your dynamic IDs

        // Create a custom filter group to filter by product IDs
        $filter = new Filter();
        $filter->setField('entity_id');
        $filter->setValue($productIds);
        $filter->setConditionType('in');

        $filterGroup = new FilterGroup();
        $filterGroup->setFilters([$filter]);

        // Add the custom filter group to the existing search criteria
        $searchCriteria->setFilterGroups([$filterGroup]);

        return $proceed($searchCriteria);
    }
}
