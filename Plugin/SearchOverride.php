<?php
namespace Gopersonal\Magento\Plugin; // Adjust the namespace if needed

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as SearchCollection;

class SearchOverride
{
    protected $fixedProductIds;

    public function __construct(
        array $fixedProductIds = [1556] // Your array of fixed product IDs
    ) {
        $this->fixedProductIds = $fixedProductIds;
    }

    public function aroundGetSelect(
        SearchCollection $subject, 
        \Closure $proceed
    ) {
        if (!empty($this->fixedProductIds)) {
            $select = $proceed();
            $select->where('e.entity_id IN (?)', $this->fixedProductIds);
            return $select;
        }
        return $proceed();
    }
}