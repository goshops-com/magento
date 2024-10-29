<?php
namespace Gopersonal\Magento\Model\ResourceModel\Fulltext\Collection;

use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection\SearchResultApplierInterface;

class CustomSearchResultApplier implements SearchResultApplierInterface
{
    public function __construct(
        SearchResultInterface $searchResult,
        Collection $collection
    ) {
        die('Constructor called!'); // This will show if the class is instantiated
    }

    public function apply()
    {
        die('Apply method called!'); // This will show if the method is called
    }
}