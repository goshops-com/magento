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
            $products = [
                [
                    'entity_id' => '1',
                    'name' => 'Test Product 1',
                    'price' => 99.99,
                    'sku' => 'TEST-1',
                    'category_ids' => [2, 3]
                ],
                [
                    'entity_id' => '2',
                    'name' => 'Test Product 2',
                    'price' => 149.99,
                    'sku' => 'TEST-2',
                    'category_ids' => [2, 4]
                ]
            ];

            // Create documents
            $documents = [];
            foreach ($products as $product) {
                $documentFields = [];
                foreach ($product as $key => $value) {
                    $documentFields[$key] = new Value($value, $key);
                }

                $documents[] = new SearchDocument($product, $documentFields);
            }

            // Create buckets properly
            $buckets = [];
            
            // Category bucket
            $categoryValues = [
                new Value(2, 'category', ['count' => 2]),
                new Value(3, 'category', ['count' => 1]),
                new Value(4, 'category', ['count' => 1])
            ];
            $buckets['category'] = new Bucket('category', 'category', $categoryValues);

            // Price bucket
            $priceValues = [
                new Value(['from' => 0, 'to' => 100], 'price', ['count' => 1]),
                new Value(['from' => 100, 'to' => 200], 'price', ['count' => 1])
            ];
            $buckets['price'] = new Bucket('price', 'price', $priceValues);

            // Create aggregation with buckets
            $aggregation = new Aggregation($buckets);

            return new QueryResponse($documents, $aggregation, count($documents));

        } catch (\Exception $e) {
            throw $e;
        }
    }
}