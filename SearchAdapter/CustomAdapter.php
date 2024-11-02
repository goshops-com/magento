<?php
namespace Gopersonal\Magento\SearchAdapter;

use Magento\Framework\Search\AdapterInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\Search\SearchResultInterfaceFactory;
use Magento\Framework\Api\Search\DocumentFactory;
use Magento\Framework\App\ObjectManager;

class CustomAdapter implements AdapterInterface
{
    private const BATCH_SIZE = 100;
    
    /**
     * @var SearchResultInterfaceFactory
     */
    private $searchResultFactory;
    
    /**
     * @var DocumentFactory
     */
    private $documentFactory;
    
    /**
     * @var array
     */
    private $productIds;
    
    /**
     * @var bool
     */
    private $isInitialized = false;

    /**
     * @param SearchResultInterfaceFactory $searchResultFactory
     * @param DocumentFactory $documentFactory
     * @param array $productIds
     */
    public function __construct(
        SearchResultInterfaceFactory $searchResultFactory,
        DocumentFactory $documentFactory,
        array $productIds = [2040]
    ) {
        $this->searchResultFactory = $searchResultFactory;
        $this->documentFactory = $documentFactory;
        $this->productIds = $productIds;
    }

    /**
     * @param \Magento\Framework\Search\RequestInterface $request
     * @return SearchResultInterface
     */
    public function query(\Magento\Framework\Search\RequestInterface $request)
    {
        // Create search result instance early to avoid DI compilation issues
        $searchResult = $this->searchResultFactory->create();
        
        if (empty($this->productIds)) {
            $searchResult->setItems([]);
            $searchResult->setTotalCount(0);
            return $searchResult;
        }

        $documents = [];
        
        // Process in batches to avoid memory issues
        foreach (array_chunk($this->productIds, self::BATCH_SIZE) as $chunk) {
            foreach ($chunk as $productId) {
                $documents[] = $this->documentFactory->create()
                    ->setId($productId)
                    ->setCustomAttribute('entity_id', $productId)
                    ->setCustomAttribute('score', 1);
            }
        }

        $searchResult->setItems($documents);
        $searchResult->setTotalCount(count($documents));
        
        return $searchResult;
    }

    /**
     * Get product IDs
     *
     * @return array
     */
    public function getProductIds(): array
    {
        return $this->productIds;
    }

    /**
     * Set product IDs
     *
     * @param array $productIds
     * @return void
     */
    public function setProductIds(array $productIds): void
    {
        $this->productIds = $productIds;
    }
}