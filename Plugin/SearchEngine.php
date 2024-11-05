<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Api\Search\Document as SearchDocument;
use Magento\Framework\Search\Response\QueryResponse;
use Magento\Framework\Search\Response\Aggregation;
use Magento\Framework\Search\Response\Aggregation\Value;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\RequestInterface as HttpRequestInterface;
use Magento\Search\Model\SearchEngine as MagentoSearchEngine;
use Magento\Framework\ObjectManagerInterface;
use Magento\Search\Model\AdapterFactory;
use Magento\Framework\Search\Dynamic\IntervalFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;

class SearchEngine extends MagentoSearchEngine
{
    protected $logger;
    protected $httpRequest;
    protected $objectManager;
    protected $productCollectionFactory;

    public function __construct(
        AdapterFactory $adapterFactory,
        IntervalFactory $intervalFactory,
        LoggerInterface $logger,
        HttpRequestInterface $httpRequest,
        ObjectManagerInterface $objectManager,
        CollectionFactory $productCollectionFactory
    ) {
        $this->logger = $logger;
        $this->httpRequest = $httpRequest;
        $this->objectManager = $objectManager;
        $this->productCollectionFactory = $productCollectionFactory;
        parent::__construct($adapterFactory, $intervalFactory);
    }

    public function search(RequestInterface $request)
    {
        if (!$this->httpRequest->getParam('gpSearchOverride')) {
            return parent::search($request);
        }

        try {
            // Get default search result for aggregations
            $defaultResult = parent::search($request);

            // Your custom product IDs
            $productIds = [1, 2]; // Your hardcoded product IDs

            // Load full product data to get attributes for layered navigation
            $collection = $this->productCollectionFactory->create();
            $collection->addAttributeToSelect('*')
                      ->addFieldToFilter('entity_id', ['in' => $productIds]);

            $documents = [];
            foreach ($collection as $product) {
                $documentFields = [];
                
                // Add all product attributes that might be used in layered navigation
                foreach ($product->getData() as $code => $value) {
                    $documentFields[$code] = new Value($value, $code);
                }

                // Essential fields for proper document handling
                $documentData = [
                    'entity_id' => $product->getId(),
                    'id' => $product->getId(),
                    '_id' => $product->getId(),
                    '_score' => 1,
                    'score' => 1,
                    'visibility' => $product->getVisibility(),
                    'status' => $product->getStatus(),
                    'type_id' => $product->getTypeId(),
                    'store_id' => $product->getStoreId(),
                    'website_ids' => $product->getWebsiteIds(),
                    '_type' => 'product',
                    '_index' => 'catalog_product',
                    '_source' => $product->getData()
                ];

                $documents[] = new SearchDocument($documentData, $documentFields);
            }

            // Use original aggregations from default search
            return new QueryResponse(
                $documents,
                $defaultResult->getAggregations(),
                count($documents)
            );

        } catch (\Exception $e) {
            $this->logger->error("Search engine error: " . $e->getMessage());
            throw $e;
        }
    }
}