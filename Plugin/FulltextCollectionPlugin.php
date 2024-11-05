<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Framework\DB\Select;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;
use Magento\Framework\App\RequestInterface;

class FulltextCollectionPlugin
{
    protected $request;

    public function __construct(
        RequestInterface $request
    ) {
        $this->request = $request;
    }

    public function beforeLoad(Collection $subject, $printQuery = false, $logQuery = false)
    {
        try {
            // Your custom product IDs
            $productIds = [1, 2]; // Replace with your hardcoded IDs

            // Reset the collection's select object
            $select = $subject->getSelect();
            
            // Clear existing where conditions and search criteria
            $select->reset(Select::WHERE);
            
            // Add our custom product IDs condition
            $select->where(
                $subject->getConnection()->quoteInto('e.entity_id IN (?)', $productIds)
            );
            
            // Reset and set the order
            $select->reset(Select::ORDER);
            $select->order(new \Zend_Db_Expr("FIELD(e.entity_id," . implode(',', $productIds) . ")"));

            // Reset search criteria
            $subject->resetData();
            
            // Ensure visibility and status filters are still applied
            $subject->addAttributeToFilter('status', 1); // Only enabled products
            $subject->addAttributeToFilter('visibility', ['neq' => 1]); // Not hidden
            
            return [$printQuery, $logQuery];
            
        } catch (\Exception $e) {
            // Log error if needed
            return [$printQuery, $logQuery];
        }
    }
}