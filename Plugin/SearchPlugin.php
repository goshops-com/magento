<?php
namespace Gopersonal\Magento\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;
use Magento\Framework\App\RequestInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Data\Collection\EntityFactory;

class SearchPlugin
{
    protected $request;
    protected $productFactory;
    protected $entityFactory;

    public function __construct(
        RequestInterface $request,
        ProductFactory $productFactory,
        EntityFactory $entityFactory
    ) {
        $this->request = $request;
        $this->productFactory = $productFactory;
        $this->entityFactory = $entityFactory;
    }

    public function aroundLoad(Collection $subject, \Closure $proceed, $printQuery = false, $logQuery = false)
    {
        if ($this->isSearchRequest()) {
            // Create the product model
            $product = $this->productFactory->create()->load(1556);
            if ($product->getId()) {
                // Clear the collection and add the product
                $subject->clear();
                $subject->addItem($product);
                $subject->setPageSize(1);
                
                // Set up dummy aggregations
                $subject->setAggregations($this->createDummyAggregations());
            }
            return $subject;
        }

        // If not a search request, proceed as usual
        return $proceed($printQuery, $logQuery);
    }

    private function isSearchRequest()
    {
        $fullActionName = $this->request->getFullActionName();
        $query = $this->request->getParam('q');
        return $fullActionName === 'catalogsearch_result_index' && !empty($query);
    }

    private function createDummyAggregations()
    {
        $aggregation = $this->entityFactory->create(AggregationInterface::class);
        // Set up any necessary dummy data for aggregations
        // For simplicity, this example does not add real data
        return $aggregation;
    }
}
