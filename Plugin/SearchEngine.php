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
    private array $facetConfig;

    public function __construct(
        AdapterFactory $adapterFactory,
        IntervalFactory $intervalFactory,
        HttpRequestInterface $request
    ) {
        $this->request = $request;
        
        // Define facet configuration similar to Algolia's approach
        $this->facetConfig = [
            'category' => [
                'type' => 'conjunctive', // Similar to Algolia's conjunctive facets
                'attribute' => 'category_ids',
                'label' => 'Category'
            ],
            'price' => [
                'type' => 'range', // Similar to Algolia's range facets
                'attribute' => 'price',
                'label' => 'Price',
                'ranges' => [
                    ['from' => 0, 'to' => 50, 'label' => 'Under $50'],
                    ['from' => 50, 'to' => 100, 'label' => '$50 - $100'],
                    ['from' => 100, 'to' => 200, 'label' => '$100 - $200'],
                    ['from' => 200, 'label' => 'Over $200']
                ]
            ],
            'brand' => [
                'type' => 'disjunctive', // Similar to Algolia's disjunctive facets
                'attribute' => 'brand',
                'label' => 'Brand'
            ]
        ];
        
        parent::__construct($adapterFactory, $intervalFactory);
    }

    public function search(RequestInterface $request)
    {
        if (!$this->request->getParam('gpSearchOverride')) {
            return parent::search($request);
        }
        
        try {
            // Sample products data - in real implementation, this would come from your data source
            $products = [
                [
                    'entity_id' => '1',
                    'name' => 'Test Product 1',
                    'price' => 99.99,
                    'sku' => 'TEST-1',
                    'category_ids' => [2, 3],
                    'brand' => 'Brand A'
                ],
                [
                    'entity_id' => '2',
                    'name' => 'Test Product 2',
                    'price' => 149.99,
                    'sku' => 'TEST-2',
                    'category_ids' => [2, 4],
                    'brand' => 'Brand B'
                ]
            ];

            // Get applied filters from request
            $appliedFilters = $this->getAppliedFilters();

            // Filter products based on applied filters
            $filteredProducts = $this->filterProducts($products, $appliedFilters);

            // Create documents
            $documents = $this->createDocuments($filteredProducts);

            // Create aggregations (facets)
            $aggregation = $this->createAggregations($filteredProducts, $appliedFilters);

            return new QueryResponse($documents, $aggregation, count($documents));

        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function getAppliedFilters(): array
    {
        $filters = [];
        
        foreach ($this->facetConfig as $facetName => $config) {
            $filterValue = $this->request->getParam($facetName);
            if ($filterValue) {
                $filters[$facetName] = is_array($filterValue) ? $filterValue : [$filterValue];
            }
        }
        
        return $filters;
    }

    private function filterProducts(array $products, array $appliedFilters): array
    {
        if (empty($appliedFilters)) {
            return $products;
        }

        return array_filter($products, function($product) use ($appliedFilters) {
            foreach ($appliedFilters as $facetName => $values) {
                $config = $this->facetConfig[$facetName];
                
                switch ($config['type']) {
                    case 'range':
                        $productValue = $product[$config['attribute']];
                        $matchesRange = false;
                        foreach ($values as $range) {
                            list($from, $to) = explode('-', $range);
                            if ($productValue >= $from && (!$to || $productValue < $to)) {
                                $matchesRange = true;
                                break;
                            }
                        }
                        if (!$matchesRange) return false;
                        break;
                        
                    case 'conjunctive':
                        // All values must match (AND condition)
                        $productValues = (array)$product[$config['attribute']];
                        foreach ($values as $value) {
                            if (!in_array($value, $productValues)) {
                                return false;
                            }
                        }
                        break;
                        
                    case 'disjunctive':
                        // Any value must match (OR condition)
                        $productValues = (array)$product[$config['attribute']];
                        $matches = false;
                        foreach ($values as $value) {
                            if (in_array($value, $productValues)) {
                                $matches = true;
                                break;
                            }
                        }
                        if (!$matches) return false;
                        break;
                }
            }
            return true;
        });
    }

    private function createDocuments(array $products): array
    {
        $documents = [];
        foreach ($products as $product) {
            $documentFields = [];
            foreach ($product as $key => $value) {
                $documentFields[$key] = new Value($value, $key);
            }
            $documents[] = new SearchDocument($product['entity_id'], $documentFields);
        }
        return $documents;
    }

    private function createAggregations(array $products, array $appliedFilters): Aggregation
    {
        $buckets = [];
        
        foreach ($this->facetConfig as $facetName => $config) {
            switch ($config['type']) {
                case 'range':
                    $buckets[$facetName] = $this->createRangeBucket($products, $config, $facetName);
                    break;
                    
                case 'conjunctive':
                case 'disjunctive':
                    $buckets[$facetName] = $this->createTermBucket($products, $config, $facetName);
                    break;
            }
        }
        
        return new Aggregation($buckets);
    }

    private function createRangeBucket(array $products, array $config, string $facetName): Bucket
    {
        $ranges = $config['ranges'];
        $values = [];
        
        foreach ($ranges as $range) {
            $count = count(array_filter($products, function($product) use ($range, $config) {
                $value = $product[$config['attribute']];
                return $value >= $range['from'] && (!isset($range['to']) || $value < $range['to']);
            }));
            
            if ($count > 0) {
                $values[] = new Value(
                    ['from' => $range['from'], 'to' => $range['to'] ?? null],
                    $facetName,
                    ['count' => $count, 'label' => $range['label']]
                );
            }
        }
        
        return new Bucket($facetName, $config['label'], $values);
    }

    private function createTermBucket(array $products, array $config, string $facetName): Bucket
    {
        $counts = [];
        foreach ($products as $product) {
            $values = (array)$product[$config['attribute']];
            foreach ($values as $value) {
                $counts[$value] = ($counts[$value] ?? 0) + 1;
            }
        }
        
        $values = [];
        foreach ($counts as $value => $count) {
            $values[] = new Value($value, $facetName, ['count' => $count]);
        }
        
        return new Bucket($facetName, $config['label'], $values);
    }
}