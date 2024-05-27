<?php
namespace Gopersonal\Magento\Plugin; 

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as SearchCollection;
use Magento\Framework\App\ResourceConnection;

class SearchOverride
{
    protected $resourceConnection;

    public function __construct(ResourceConnection $resourceConnection) 
    {
        $this->resourceConnection = $resourceConnection;
    }

    public function aroundGetSelect(SearchCollection $subject, \Closure $proceed) 
    {
        $select = $proceed();

        $fixedProductIds = [123, 456, 789];

        if (!empty($fixedProductIds)) { // Removed the $this-> from fixedProductIds
            $unionSelect = $this->resourceConnection->getConnection()->select()
                ->from(['fixed_products' => $subject->getMainTable()])
                ->where('fixed_products.entity_id IN (?)', $fixedProductIds); // Removed the $this-> from fixedProductIds

            $select->union([$unionSelect]);
        }

        return $select;
    }
}
