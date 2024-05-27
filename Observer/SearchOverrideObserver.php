<?php
namespace Gopersonal\Magento\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class SearchOverrideObserver implements ObserverInterface
{
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function execute(Observer $observer)
    {
        // Log the function call
        $this->logger->info('SearchOverrideObserver called.');

        $collection = $observer->getData('collection');
        if (!$collection) {
            $this->logger->info('No collection found.');
            return;
        }

        $hardcodedProductId = 1556;

        $this->logger->info('Filtering search results by product ID: ' . $hardcodedProductId);

        // Apply filter and log the collection count
        $collection->addFieldToFilter('entity_id', $hardcodedProductId);
        $collectionSize = $collection->getSize();
        $this->logger->info('Collection size after filter: ' . $collectionSize);

        // Log the actual SQL query being executed
        $this->logger->info('SQL query: ' . $collection->getSelect()->__toString());
    }
}
