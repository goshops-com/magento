<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Framework\Search\RequestInterface;
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
    protected $adapterFactory;
    protected $intervalFactory;
    protected $collectionFactory;

    public function __construct(
        AdapterFactory $adapterFactory,
        IntervalFactory $intervalFactory,
        LoggerInterface $logger,
        HttpRequestInterface $httpRequest,
        ObjectManagerInterface $objectManager,
        CollectionFactory $collectionFactory
    ) {
        $this->adapterFactory = $adapterFactory;
        $this->intervalFactory = $intervalFactory;
        $this->logger = $logger;
        $this->httpRequest = $httpRequest;
        $this->objectManager = $objectManager;
        $this->collectionFactory = $collectionFactory;
        parent::__construct($adapterFactory, $intervalFactory);
    }

    public function search(RequestInterface $request)
    {
        if (!$this->httpRequest->getParam('gpSearchOverride')) {
            return parent::search($request);
        }
        
        try {
            // Get your specific product IDs
            $productIds = ['1', '2'];
            
            // Create a product collection filtered by these IDs
            $collection = $this->collectionFactory->create();
            $collection->addAttributeToFilter('entity_id', ['in' => $productIds]);
            
            // Let Magento handle the search using this filtered collection
            $collection->addSearchFilter($request->getQuery()->getQueryText());
            
            return parent::search($request);

        } catch (\Exception $e) {
            $this->logger->error("Search engine error: " . $e->getMessage());
            return parent::search($request);
        }
    }
}