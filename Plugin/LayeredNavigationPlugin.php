<?php
namespace Gopersonal\Magento\Plugin;

use Psr\Log\LoggerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Catalog\Model\Layer;

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

    public function afterGetProductCollection(Layer $subject, $result)
    {
        var_dump("LayeredNavigationPlugin - afterGetProductCollection called");
            
        if ($this->request->getParam('gpSearchOverride')) {
            var_dump("Custom search is active in layered navigation");
            
            // Get product IDs from request
            $customProductIds = $this->request->getParam('custom_product_ids');
            
            if ($customProductIds) {
                var_dump("Found custom product IDs:", $customProductIds);
                var_dump("Before filter query: " . $result->getSelect()->__toString());
                
                // Apply custom product IDs filter
                $result->addFieldToFilter('entity_id', ['in' => $customProductIds]);
                
                var_dump("After filter query: " . $result->getSelect()->__toString());
            }
        }

        return $result;
    }
}