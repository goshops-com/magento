<?php
namespace Gopersonal\Magento\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;
use Magento\Framework\App\RequestInterface;
use Magento\Catalog\Model\ProductFactory;

class SearchPlugin
{
    protected $request;
    protected $productFactory;

    public function __construct(
        RequestInterface $request,
        ProductFactory $productFactory
    ) {
        $this->request = $request;
        $this->productFactory = $productFactory;
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
}
