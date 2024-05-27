<?php
namespace Gopersonal\Magento\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;

class SearchOverride
{
    public function beforeAddSearchFilter(Collection $subject, $query)
    {
        // Override the search query to always return a hardcoded product by ID
        $hardcodedProductId = 1556;
        $subject->addFieldToFilter('entity_id', $hardcodedProductId);

        // Return an empty array to prevent the original query from being added
        return [''];
    }
}
