<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Catalog\Model\Layer\Filter\DataProvider\Price;
use Magento\Framework\Registry;
use Psr\Log\LoggerInterface;

class LayeredNavigationPlugin
{
    protected $registry;
    protected $logger;

    public function __construct(
        Registry $registry,
        LoggerInterface $logger
    ) {
        $this->registry = $registry;
        $this->logger = $logger;
    }

    public function beforeGetCollection(
        \Magento\Catalog\Model\Layer\Filter\AbstractFilter $subject
    ) {
        var_dump("LayeredNavigationPlugin - beforeGetCollection called");
        
        // Get the request object
        $request = \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Magento\Framework\App\RequestInterface::class);
            
        if ($request->getParam('gpSearchOverride')) {
            var_dump("Custom search is active in layered navigation");
            
            // Get the collection
            $collection = $subject->getLayer()->getProductCollection();
            
            // Get product IDs from registry (we'll set this in SearchEngine)
            $customProductIds = $this->registry->registry('custom_search_product_ids');
            
            if ($customProductIds) {
                var_dump("Found custom product IDs:", $customProductIds);
                
                // Apply custom product IDs filter
                $collection->addFieldToFilter('entity_id', ['in' => $customProductIds]);
            }
        }
    }
}