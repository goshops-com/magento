<?php

namespace Gopersonal\Magento\Plugin;

use Magento\Search\Model\SearchEngine;
use Magento\Framework\Search\RequestInterface;
use Gopersonal\Magento\Model\CustomSearch;
use Psr\Log\LoggerInterface;

class SearchEnginePlugin
{
    protected $customSearch;
    protected $logger;

    public function __construct(
        CustomSearch $customSearch,
        LoggerInterface $logger
    ) {
        $this->customSearch = $customSearch;
        $this->logger = $logger;
    }

    public function aroundSearch(
        SearchEngine $subject,
        callable $proceed,
        RequestInterface $request
    ) {
        $searchCriteria = $this->convertRequestToSearchCriteria($request);
        $searchTerm = $this->getQueryFromSearchCriteria($searchCriteria);

        // Check if the request is from a category page or if there is no search term
        if ($this->isCategoryPage($searchCriteria) || empty($searchTerm)) {
            // Let Magento handle category page requests using the default search engine
            $this->logger->info('SearchEnginePlugin: Handling category page request with default search engine.');
            return $proceed($request);
        }

        // Use the custom search logic for search queries
        $this->logger->info('SearchEnginePlugin: Handling search query with custom behavior.');
        return $this->customSearch->search($searchCriteria);
    }

    private function convertRequestToSearchCriteria(RequestInterface $request) {
        // Assuming that your CustomSearch class can work with SearchCriteriaInterface.
        // You need to convert the RequestInterface to SearchCriteriaInterface.
        // This is a simplified version, you might need to adjust according to your needs.
        $searchCriteriaBuilder = $this->customSearch->getSearchCriteriaBuilder();
        $searchCriteriaBuilder->setRequestName($request->getName());
        
        foreach ($request->getParams() as $key => $value) {
            $searchCriteriaBuilder->addFilter($key, $value);
        }

        return $searchCriteriaBuilder->create();
    }

    private function getQueryFromSearchCriteria($searchCriteria) {
        foreach ($searchCriteria->getFilterGroups() as $group) {
            foreach ($group->getFilters() as $filter) {
                if ($filter->getField() === 'search_term') {
                    return $filter->getValue();
                }
            }
        }
        return null;
    }

    private function isCategoryPage($searchCriteria) {
        foreach ($searchCriteria->getFilterGroups() as $group) {
            foreach ($group->getFilters() as $filter) {
                if ($filter->getField() === 'category_ids') {
                    return true;
                }
            }
        }
        return false;
    }
}
