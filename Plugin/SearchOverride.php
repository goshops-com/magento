<?php
namespace Gopersonal\Magento\Plugin; // Adjust the namespace if needed

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as SearchCollection;
use Magento\Framework\App\ResourceConnection;

class SearchOverride
{
    protected $fixedProductIds = [123, 456, 789]; // Your array of fixed product IDs
    protected $resourceConnection;

    public function __construct(
        array $fixedProductIds = [],
        ResourceConnection $resourceConnection
    ) {
        $this->fixedProductIds = $fixedProductIds;
        $this->resourceConnection = $resourceConnection;
    }

    public function aroundGetSelect(
        SearchCollection $subject,
        \Closure $proceed
    ) {
        $select = $proceed();

        if (!empty($this->fixedProductIds)) {
            $connection = $this->resourceConnection->getConnection();
            $unionSelect = $connection->select()
                ->from(['fixed_products' => $subject->getMainTable()], null) // Assuming product flat table is the main table
                ->where('fixed_products.entity_id IN (?)', $this->fixedProductIds);
            $select->union([$unionSelect]);
        }

        return $select;
    }
}
