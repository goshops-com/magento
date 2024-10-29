<?php
namespace Gopersonal\Magento\Model\ResourceModel\Fulltext\Collection;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection\SearchResultApplier;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;

class CustomSearchResultApplier extends SearchResultApplier
{
    /**
     * @var SearchResultInterface
     */
    private $searchResult;

    /**
     * @var Collection
     */
    private $collection;

    /**
     * @param SearchResultInterface $searchResult
     * @param Collection $collection
     */
    public function __construct(
        SearchResultInterface $searchResult,
        Collection $collection
    ) {
        $this->searchResult = $searchResult;
        $this->collection = $collection;
    }

    public function apply()
    {
        // Hardcoded product ID
        $productIds = [2040];
        
        if (empty($productIds)) {
            $this->collection->getSelect()->where('e.entity_id IN (0)');
            return;
        }

        $this->collection->getSelect()->where('e.entity_id IN (?)', $productIds);
    }
}