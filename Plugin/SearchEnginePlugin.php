<?php

namespace Gopersonal\Magento\Plugin;

use Magento\Search\Model\SearchEngine;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use Magento\Catalog\Model\Session as CatalogSession;
use Gopersonal\Magento\Model\CustomSearch;
use Psr\Log\LoggerInterface;

class SearchEnginePlugin
{
    protected $catalogSession;
    protected $customSearch;
    protected $logger;

    public function __construct(
        CatalogSession $catalogSession,
        CustomSearch $customSearch,
        LoggerInterface $logger
    ) {
        $this->catalogSession = $catalogSession;
        $this->customSearch = $customSearch;
        $this->logger = $logger;
    }

    public function aroundSearch(
        SearchEngine $subject,
        callable $proceed,
        SearchCriteriaInterface $searchCriteria
    ) {
        $searchTerm = $this->getQueryFromSearchCriteria($searchCriteria);
        // Check if the request is from a category page
        if ($this->isCategoryPage($searchCriteria) || empty($searchTerm)) {
            // Let Magento handle category page requests using the default search engine
            $this->logger->info('SearchEnginePlugin: Handling category page request with default search engine.');
            return $proceed($searchCriteria);
        }

        // Use the custom search logic for search queries
        $this->logger->info('SearchEnginePlugin: Handling search query with custom behavior.');
        return $this->customSearch->search($searchCriteria);
    }

    private function getQueryFromSearchCriteria(SearchCriteriaInterface $searchCriteria) {
        foreach ($searchCriteria->getFilterGroups() as $group) {
            foreach ($group->getFilters() as $filter) {
                if ($filter->getField() === 'search_term') {
                    return $filter->getValue();
                }
            }
        }
        return null;
    }

    private function isCategoryPage(SearchCriteriaInterface $searchCriteria)
    {
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
