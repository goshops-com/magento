<?php
namespace Gopersonal\Magento\Model;

use Magento\Search\Api\SearchInterface;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use Magento\Search\Api\SearchInterface as DefaultSearchInterface;

class SearchHandler implements SearchInterface {
    protected $defaultSearchEngine;

    public function __construct(
        DefaultSearchInterface $defaultSearchEngine // This is the actual search engine interface provided by Magento
    ) {
        $this->defaultSearchEngine = $defaultSearchEngine;
    }

    public function search(SearchCriteriaInterface $searchCriteria) {
        // Delegate the search to Magento's default search engine
        return $this->defaultSearchEngine->search($searchCriteria);
    }
}
