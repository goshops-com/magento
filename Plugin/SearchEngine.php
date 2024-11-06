<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\Response\QueryResponse;
use Magento\Framework\Search\Response\Aggregation;
use Magento\Framework\Search\Response\Aggregation\Value;
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

    protected function logAggregations(Aggregation $aggregations, $source = 'unknown') 
    {
        $debugData = [
            'source' => $source,
            'buckets' => []
        ];

        // Log bucket values
        foreach ($aggregations->getBuckets() as $bucketName => $bucket) {
            $debugData['buckets'][$bucketName] = [
                'name' => $bucketName,
                'values' => []
            ];
            
            foreach ($bucket->getValues() as $value) {
                if ($value instanceof Value) {
                    $debugData['buckets'][$bucketName]['values'][] = [
                        'value' => $value->getValue(),
                        'metrics' => $value->getMetrics(),
                    ];
                } else {
                    $debugData['buckets'][$bucketName]['values'][] = $value;
                }
            }
        }

        $this->logger->debug('SEARCH ENGINE AGGREGATIONS DEBUG: ' . json_encode($debugData, JSON_PRETTY_PRINT));
    }

    public function search(RequestInterface $request)
    {
        if (!$this->httpRequest->getParam('gpSearchOverride')) {
            $this->logger->debug("USING DEFAULT MAGENTO SEARCH");
            
            // Get default search results
            $result = parent::search($request);
            
            // Log the aggregations from default search
            if ($result instanceof QueryResponse) {
                $this->logAggregations($result->getAggregations(), 'default_search');
            }
            
            return $result;
        }

        $this->logger->debug("USING CUSTOM SEARCH ENGINE");
        
        try {
            // Rest of your custom search implementation remains the same
            $products = [
                [
                    'entity_id' => '1',
                    'name' => 'Test Product 1',
                    'price' => 99.99,
                    'sku' => 'TEST-1'
                ],
                [
                    'entity_id' => '2',
                    'name' => 'Test Product 2',
                    'price' => 149.99,
                    'sku' => 'TEST-2'
                ]
            ];

            // Create documents...
            $documents = [];
            // Your existing document creation code...

            // Log the request object to see what aggregations are being requested
            $this->logger->debug('Search Request Debug: ' . json_encode([
                'request_name' => $request->getName(),
                'dimensions' => $request->getDimensions(),
                'query' => $request->getQuery()->__toString(),
                'buckets' => array_keys($request->getAggregation())
            ], JSON_PRETTY_PRINT));

            $response = parent::search($request);
            
            // Log the aggregations from default search for comparison
            if ($response instanceof QueryResponse) {
                $this->logAggregations($response->getAggregations(), 'custom_search');
            }
            
            return $response;

        } catch (\Exception $e) {
            $this->logger->error("Error in search engine: " . $e->getMessage());
            throw $e;
        }
    }
}