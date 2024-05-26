<?php
namespace Gopersonal\Magento\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;

class SearchPlugin
{
    protected $request;
    protected $storeManager;

    public function __construct(RequestInterface $request, StoreManagerInterface $storeManager)
    {
        $this->request = $request;
        $this->storeManager = $storeManager;
    }

    public function aroundLoad(Collection $subject, \Closure $proceed, $printQuery = false, $logQuery = false)
    {
        // Check if the current request is a search request
        if ($this->isSearchRequest()) {
            // Override the search result
            $subject->getSelect()->reset(\Magento\Framework\DB\Select::FROM);
            $subject->getSelect()->reset(\Magento\Framework\DB\Select::COLUMNS);
            $subject->getSelect()->reset(\Magento\Framework\DB\Select::WHERE);
            $subject->getSelect()->from(
                ['e' => $subject->getTable('catalog_product_entity')],
                ['entity_id']
            );
            $subject->getSelect()->where('e.entity_id = ?', 1556);

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
