<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Framework\DB\Select;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;
use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface;

class FulltextCollectionPlugin
{
    protected $request;
    protected $logger;

    public function __construct(
        RequestInterface $request,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->logger = $logger;
    }

    public function beforeLoad(Collection $subject, $printQuery = false, $logQuery = false)
    {
        try {
            $productIds = [1, 2];
            $select = $subject->getSelect();
            
            // Log initial state
            $this->logger->info('Before modification: ' . $select->__toString());
            
            $select->reset(Select::WHERE);
            $select->where($subject->getConnection()->quoteInto('e.entity_id IN (?)', $productIds));
            $select->reset(Select::ORDER);
            $select->order(new \Zend_Db_Expr("FIELD(e.entity_id," . implode(',', $productIds) . ")"));
            
            // Log after our modifications
            $this->logger->info('After our modification: ' . $select->__toString());
            
            return [$printQuery, $logQuery];
        } catch (\Exception $e) {
            $this->logger->error('Error in search plugin: ' . $e->getMessage());
            return [$printQuery, $logQuery];
        }
    }

    public function afterLoad(Collection $subject, $result)
    {
        // Log final query that was actually executed
        $this->logger->info('Final executed query: ' . $subject->getSelect()->__toString());
        
        // Log the count of products
        $this->logger->info('Final product count: ' . $subject->count());
        
        // Log product IDs in final result
        $productIds = [];
        foreach ($subject as $product) {
            $productIds[] = $product->getId();
        }
        $this->logger->info('Final product IDs: ' . implode(',', $productIds));
        
        return $result;
    }

    // Add around plugin to catch any modifications between before and after
    public function aroundLoad(Collection $subject, callable $proceed, $printQuery = false, $logQuery = false)
    {
        $this->logger->info('----------- Starting Load Process -----------');
        
        // Before the main load
        $select = $subject->getSelect();
        $this->logger->info('Before proceed: ' . $select->__toString());
        
        // Call the original method
        $result = $proceed($printQuery, $logQuery);
        
        // After the main load
        $this->logger->info('After proceed: ' . $subject->getSelect()->__toString());
        $this->logger->info('----------- Ending Load Process -----------');
        
        return $result;
    }
}