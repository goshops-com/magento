<?php
namespace Gopersonal\Magento\Plugin;

use Psr\Log\LoggerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Catalog\Model\Layer;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;

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

    public function beforePrepareProductCollection(Layer $subject, Collection $collection)
    {
        var_dump("LayeredNavigationPlugin - beforePrepareProductCollection called");
            
        if ($this->request->getParam('gpSearchOverride')) {
            var_dump("Custom search is active in layered navigation");
            
            $customProductIds = $this->request->getParam('custom_product_ids');
            
            if ($customProductIds) {
                var_dump("Found custom product IDs:", $customProductIds);
                var_dump("Before prepare collection query: " . $collection->getSelect()->__toString());
                
                // Add our filter before Magento prepares the collection
                $collection->addFieldToFilter('entity_id', ['in' => $customProductIds]);
                
                var_dump("After prepare collection query: " . $collection->getSelect()->__toString());
            }
        }
    }
}