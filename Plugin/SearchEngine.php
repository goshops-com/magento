<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\Request\Query\Filter;
use Magento\Framework\Search\Request\Query\BoolExpression;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\RequestInterface as HttpRequestInterface;
use Magento\Search\Model\SearchEngine as MagentoSearchEngine;
use Magento\Framework\ObjectManagerInterface;
use Magento\Search\Model\AdapterFactory;
use Magento\Framework\Search\Dynamic\IntervalFactory;

class SearchEngine extends MagentoSearchEngine
{
    protected $logger;
    protected $httpRequest;
    protected $objectManager;
    protected $adapterFactory;
    protected $intervalFactory;

    public function __construct(
        AdapterFactory $adapterFactory,
        IntervalFactory $intervalFactory,
        LoggerInterface $logger,
        HttpRequestInterface $httpRequest,
        ObjectManagerInterface $objectManager
    ) {
        $this->adapterFactory = $adapterFactory;
        $this->intervalFactory = $intervalFactory;
        $this->logger = $logger;
        $this->httpRequest = $httpRequest;
        $this->objectManager = $objectManager;
        parent::__construct($adapterFactory, $intervalFactory);
    }

    public function search(RequestInterface $request)
    {
        if (!$this->httpRequest->getParam('gpSearchOverride')) {
            var_dump("USING DEFAULT MAGENTO SEARCH");
            return parent::search($request);
        }

        var_dump("USING CUSTOM SEARCH ENGINE");
        
        try {
            // Your custom product IDs
            $productIds = ['1', '2'];
            
            // Create a filter query for entity_id
            $filterQuery = new Filter(
                'entity_id_filter',    // name
                'terms',               // type
                'entity_id',           // field
                $productIds            // value
            );

            // Get original query from request
            $originalQuery = $request->getQuery();
            
            // Combine with original query
            $newQuery = new BoolExpression(
                'combined_filter',
                [
                    'must' => [$filterQuery],
                    'should' => $originalQuery ? [$originalQuery] : []
                ]
            );

            // Create a new request with our modified query
            $reflectionClass = new \ReflectionClass($request);
            $queryProperty = $reflectionClass->getProperty('query');
            $queryProperty->setAccessible(true);
            $queryProperty->setValue($request, $newQuery);

            // Let parent handle everything with our modified request
            return parent::search($request);

        } catch (\Exception $e) {
            $this->logger->error("Search engine error: " . $e->getMessage());
            return parent::search($request);
        }
    }
}