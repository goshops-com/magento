<?php
namespace Gopersonal\Magento\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;
use Psr\Log\LoggerInterface;

class SearchOverridePlugin
{
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function aroundGetItems(Collection $subject, callable $proceed)
    {
        // Log the function call
        $this->logger->info('SearchOverridePlugin aroundGetItems called.');

        $hardcodedProductId = 1556;

        // Clone the collection to avoid modifying the original one directly
        $clonedCollection = clone $subject;
        $clonedCollection->clear();

        // Apply filter to the cloned collection
        $clonedCollection->addFieldToFilter('entity_id', $hardcodedProductId);
        $clonedCollection->load();

        $this->logger->info('Collection size after filter: ' . $clonedCollection->getSize());
        $this->logger->info('SQL query: ' . $clonedCollection->getSelect()->__toString());

        // Return the filtered items
        return $clonedCollection->getItems();
    }
}
