<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Api\Search\Document as SearchDocument;
use Magento\Framework\Search\Response\QueryResponse;
use Magento\Framework\Search\Response\Aggregation;
use Magento\Framework\Search\Response\Aggregation\Value;
use Magento\Framework\Search\Response\Bucket;
use Magento\Framework\App\RequestInterface as HttpRequestInterface;
use Magento\Search\Model\SearchEngine as MagentoSearchEngine;
use Magento\Search\Model\AdapterFactory;
use Magento\Framework\Search\Dynamic\IntervalFactory;

class SearchEngine extends MagentoSearchEngine
{
    private HttpRequestInterface $request;

    public function __construct(
        AdapterFactory $adapterFactory,
        IntervalFactory $intervalFactory,
        HttpRequestInterface $request
    ) {
        $this->request = $request;
        parent::__construct($adapterFactory, $intervalFactory);
    }

    public function search(RequestInterface $request)
    {
        if (!$this->request->getParam('gpSearchOverride')) {
            return parent::search($request);
        }
        
        try {
            // Get your filtered product IDs (example data)
            $productIds = [
                '1' => [
                    'entity_id' => '1',
                    'name' => 'Test Product 1',
                    'price' => 99.99,
                    'sku' => 'TEST-1',
                ],
                '2' => [
                    'entity_id' => '2', 
                    'name' => 'Test Product 2',
                    'price' => 149.99,
                    'sku' => 'TEST-2',
                ]
            ];

            // Create documents just with entity_id
            $documents = [];
            foreach ($productIds as $id => $data) {
                $documentFields = [];
                foreach ($data as $key => $value) {
                    $documentFields[$key] = new Value($value, $key);
                }
                $documents[] = new SearchDocument($id, $documentFields);
            }

            // Get parent search results for aggregations
            $parentResponse = parent::search($request);
            
            // Use parent's aggregations
            return new QueryResponse(
                $documents,
                $parentResponse->getAggregations(),
                count($documents)
            );

        } catch (\Exception $e) {
            throw $e;
        }
    }
}