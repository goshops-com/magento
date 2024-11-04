<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Catalog\Model\Layer\Filter\DataProvider\Price;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\RequestInterface;

class LayeredNavigationPlugin
{
    protected $logger;
    protected $request;

    public function __construct(
        LoggerInterface $logger,
        RequestInterface $request
    ) {
        $this->logger = $logger;
        $this->request = $request;
    }

    public function beforeGetCollection(
        \Magento\Catalog\Model\Layer\Filter\AbstractFilter $subject
    ) {
        var_dump("LayeredNavigationPlugin - beforeGetCollection called");
            
        if ($this->request->getParam('gpSearchOverride')) {
            var_dump("Custom search is active in layered navigation");
            
            // Get the collection
            $collection = $subject->getLayer()->getProductCollection();
            
            // Get product IDs from request
            $customProductIds = $this->request->getParam('custom_product_ids');
            
            if ($customProductIds) {
                var_dump("Found custom product IDs:", $customProductIds);
                
                // Apply custom product IDs filter
                $collection->addFieldToFilter('entity_id', ['in' => $customProductIds]);
            }
        }
    }
}