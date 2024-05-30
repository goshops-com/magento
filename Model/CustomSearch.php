<?php

namespace Gopersonal\Magento\Model;

use Magento\Framework\Api\Search\SearchResultFactory;
use Magento\Search\Model\SearchEngine;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\Search\SearchResultInterfaceFactory;

class CustomSearch
{
    protected $searchEngine;
    protected $searchResultFactory;

    public function __construct(
        SearchEngine $searchEngine,
        SearchResultFactory $searchResultFactory
    ) {
        $this->searchEngine = $searchEngine;
        $this->searchResultFactory = $searchResultFactory;
    }

    public function search(SearchCriteriaInterface $searchCriteria)
    {
        // Create a hardcoded search result with product ID 1556
        $searchResult = $this->searchResultFactory->create();
        $searchResult->setItems([$this->createHardcodedProduct()]);
        $searchResult->setTotalCount(1);

        return $searchResult;
    }

    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        // Call the search method for consistency
        return $this->search($searchCriteria);
    }

    protected function createHardcodedProduct()
    {
        return new \Magento\Framework\DataObject(['entity_id' => 1556]);
    }
}
