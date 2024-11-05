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
        if (!$this->request->getParam('gpSearchOverride')) {
            return [$printQuery, $logQuery];
        }

        try {
            // Your custom product IDs
            $productIds = [1, 2]; // Replace with your hardcoded IDs

            // Get the collection's select object
            $select = $subject->getSelect();
            
            // Reset any existing where conditions on e.entity_id
            $where = $select->getPart(Select::WHERE);
            $where = array_filter($where, function($condition) {
                return !strpos($condition, 'e.entity_id');
            });
            
            // Add our custom product IDs condition
            $where[] = $subject->getConnection()->quoteInto('e.entity_id IN (?)', $productIds);
            
            // Set the modified where conditions back to the select
            $select->setPart(Select::WHERE, $where);
            
            return [$printQuery, $logQuery];
            
        } catch (\Exception $e) {
            // Log error if needed
            return [$printQuery, $logQuery];
        }
    }
}