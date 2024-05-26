<?php
namespace Gopersonal\Magento\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;
use Psr\Log\LoggerInterface;

class SearchPlugin
{
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function beforeAddSearchFilter(Collection $subject, $query)
    {
        $this->logger->info('SearchPlugin beforeAddSearchFilter called with query: ' . $query);

        // Hardcode the product ID for testing purposes
        $hardcodedProductIds = [1556];

        $this->logger->info('Filtering search results to include only product ID: ' . implode(', ', $hardcodedProductIds));

        $subject->addFieldToFilter('entity_id', ['in' => $hardcodedProductIds]);

        return [$query];
    }
}
