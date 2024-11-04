<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Catalog\Model\Layer;
use Psr\Log\LoggerInterface;

class LayerPlugin
{
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function afterGetProductCollection(Layer $subject, $result)
    {
        // var_dump("LAYER PRODUCT COLLECTION SIZE: " . $result->getSize());
        // var_dump("LAYER COLLECTION QUERY: " . $result->getSelect()->__toString());
        
        return $result;
    }
}