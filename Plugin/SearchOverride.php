<?php
namespace Gopersonal\Magento\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;
use Psr\Log\LoggerInterface;

class SearchOverride
{
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function beforeAddSearchFilter(Collection $subject, $query)
    {
        // Log the function call
        $this->logger->info('SearchOverride plugin called.');
        $this->logger->info('Original query: ' . $query);

        // Override the search query to always return a hardcoded product by ID
        $hardcodedProductId = 1556;
        $this->logger->info('Filtering search results by product ID: ' . $hardcodedProductId);

        // Apply filter and log the collection count
        $subject->addFieldToFilter('entity_id', $hardcodedProductId);
        $collectionSize = $subject->getSize();
        $this->logger->info('Collection size after filter: ' . $collectionSize);

        // Log the actual SQL query being executed
        $this->logger->info('SQL query: ' . $subject->getSelect()->__toString());

        // Return an empty array to prevent the original query from being added
        return [''];
    }
}
