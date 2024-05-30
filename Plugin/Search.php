<!-- app/code/Gopersonal/Magento/Plugin/Search.php -->
<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Search\Api\SearchInterface;
use Magento\Framework\Api\Search\Document;

class Search
{
    public function aroundSearch(
        SearchInterface $subject,
        callable $proceed,
        \Magento\Framework\Api\Search\SearchCriteriaInterface $searchCriteria
    ) {
        // Get the original search results
        $searchResult = $proceed($searchCriteria);

        // Create a new fixed product result
        $fixedProductId = 1556; // Fixed product ID
        $document = new Document(['id' => $fixedProductId]);

        // Set the fixed result
        $searchResult = new \Magento\Framework\Api\Search\SearchResult();
        $searchResult->setItems([$document]);
        $searchResult->setTotalCount(1);

        return $searchResult;
    }
}
