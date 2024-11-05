<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\Request\QueryInterface;
use Magento\Framework\Search\Request\Query\BoolExpression;
use Magento\Framework\Search\Request\Query\Filter;
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
            $idQuery = new Filter(
                'entity_id_filter',
                QueryInterface::QUERY_FILTER,
                [
                    'field' => 'entity_id',
                    'value' => $productIds,
                    'type' => 'terms'
                ]
            );

            // Get original query from request
            $originalQuery = $request->getQuery();
            
            // Combine with existing query if needed
            if ($originalQuery) {
                $newQuery = new BoolExpression(
                    'combined_filter',
                    [
                        'must' => [$idQuery],
                        'should' => [$originalQuery]
                    ]
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