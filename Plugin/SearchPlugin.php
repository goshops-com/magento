<?php
namespace Gopersonal\Magento\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;
use Magento\Framework\App\RequestInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;

class SearchPlugin
{
    protected $request;
    protected $productCollectionFactory;

    public function __construct(
        RequestInterface $request,
        ProductCollectionFactory $productCollectionFactory
    ) {
        $this->request = $request;
        $this->productCollectionFactory = $productCollectionFactory;
    }

    public function aroundLoad(Collection $subject, \Closure $proceed, $printQuery = false, $logQuery = false)
    {
        if ($this->isSearchRequest()) {
            // Override the search result
            $productCollection = $this->productCollectionFactory->create();
            $productCollection->addFieldToFilter('entity_id', 1556);
            $subject->clear()->setItems($productCollection->getItems());

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
}
