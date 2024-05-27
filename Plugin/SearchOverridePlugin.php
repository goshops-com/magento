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

    public function beforeLoad(Collection $subject, $printQuery = false, $logQuery = false)
    {
        // Log the function call
        $this->logger->info('SearchOverridePlugin called.');

        $hardcodedProductId = 1556;

        $this->logger->info('Filtering search results by product ID: ' . $hardcodedProductId);

        // Apply filter and log the collection count
        $subject->addFieldToFilter('entity_id', $hardcodedProductId);
        $collectionSize = $subject->getSize();
        $this->logger->info('Collection size after filter: ' . $collectionSize);

        // Log the actual SQL query being executed
        $this->logger->info('SQL query: ' . $subject->getSelect()->__toString());

        return [$printQuery, $logQuery];
    }
}
