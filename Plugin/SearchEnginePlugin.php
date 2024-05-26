<?php
namespace Gopersonal\Magento\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;

class SearchPlugin
{
    public function beforeAddSearchFilter(Collection $subject, $query)
    {
        // Hardcode the product ID for testing purposes
        $hardcodedProductIds = [1556];

        $subject->addFieldToFilter('entity_id', ['in' => $hardcodedProductIds]);

        return [$query];
    }
}
