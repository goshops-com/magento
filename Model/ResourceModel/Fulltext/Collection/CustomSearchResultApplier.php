<?php
<?php
namespace Gopersonal\Magento\Model\ResourceModel\Fulltext\Collection;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection\SearchResultApplier;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;
use Psr\Log\LoggerInterface;

class CustomSearchResultApplier extends SearchResultApplier
{
    /**
     * @var SearchResultInterface
     */
    private $searchResult;

    /**
     * @var Collection
     */
    private $collection;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param SearchResultInterface $searchResult
     * @param Collection $collection
     * @param LoggerInterface $logger
     */
    public function __construct(
        SearchResultInterface $searchResult,
        Collection $collection,
        LoggerInterface $logger
    ) {
        parent::__construct($searchResult, $collection);
        $this->searchResult = $searchResult;
        $this->collection = $collection;
        $this->logger = $logger;
    }

    public function apply()
    {
        $this->logger->info('CustomSearchResultApplier is being called');
        
        // Force clear any existing where conditions
        $this->collection->getSelect()->reset(\Zend_Db_Select::WHERE);
        
        // Hardcoded product ID
        $productIds = [2040];
        
        // Log the SQL query before
        $this->logger->info('Query before: ' . $this->collection->getSelect()->__toString());
        
        if (empty($productIds)) {
            $this->collection->getSelect()->where('e.entity_id IN (0)');
            return;
        }

        // Add our where condition
        $this->collection->getSelect()->where('e.entity_id IN (?)', $productIds);
        
        // Log the final SQL query
        $this->logger->info('Query after: ' . $this->collection->getSelect()->__toString());
    }
}