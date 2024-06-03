<?php
namespace Gopersonal\Magento\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\ObjectManager;

class BeforeSearchRequest implements ObserverInterface
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

    public function execute(Observer $observer)
    {
        // Read the 'q' and '_gsSearchId' parameters from the URL
        $queryParam = $this->request->getParam('q');
        $gsSearchIdParam = $this->request->getParam('_gsSearchId');

        // Log the parameters
        $this->logger->info("Query Param: $queryParam, GS Search ID Param: $gsSearchIdParam");

        // Get product IDs using the helper
        $objectManager = ObjectManager::getInstance();
        $helper = $objectManager->get(\Gopersonal\Magento\Helper\Data::class);
        $productIds = $helper->getProductsIds('layer');

        // Log the product IDs
        $this->logger->info("Product IDs: " . implode(',', $productIds));

        return $this;
    }
}
