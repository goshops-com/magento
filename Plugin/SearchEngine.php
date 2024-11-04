<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\Request\FilterInterface;
use Magento\Framework\Search\Request\Filter\Term as TermFilter;
use Magento\Framework\Search\Request\BoolExpression;
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
            
            // Create a new filter for entity_id IN (your ids)
            $idFilter = new TermFilter(
                'entity_id',
                $productIds,
                FilterInterface::FILTER_TYPE_TERM
            );

            // Add or modify the filter in the request
            $originalQuery = $request->getQuery();
            
            // Combine your filter with existing query if needed
            if ($originalQuery) {
                $newQuery = new BoolExpression(
                    [
                        $originalQuery,
                        $idFilter
                    ],
                    BoolExpression::MUST
                );
                
                // Set the modified query back to request
                $request->setQuery($newQuery);
            }

            // Let parent handle everything with our modified request
            return parent::search($request);

        } catch (\Exception $e) {
            var_dump("Error in search engine:", $e->getMessage());
            throw $e;
        }
    }
}