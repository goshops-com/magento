<?php

namespace Gopersonal\Magento\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as SearchCollection;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Request\Http;

class SearchOverride
{
    protected $resourceConnection;
    protected $request;

    public function __construct(ResourceConnection $resourceConnection, Http $request)
    {
        $this->resourceConnection = $resourceConnection;
        $this->request = $request;
    }

    public function aroundLoad(
        SearchCollection $subject,
        \Closure $proceed,
        $printQuery = false,
        $logQuery = false
    ) {
        $searchQuery = $this->request->getParam('q'); // Get the search query parameter

        if ($searchQuery) {
            $fixedProductIds = [1556]; // Your array of fixed product IDs (or fetch dynamically)

            if (!empty($fixedProductIds)) {
                $subject->getSelect()->reset(\Zend_Db_Select::WHERE);
                $subject->getSelect()->where('e.entity_id IN (?)', $fixedProductIds);
            }
        }

        return $proceed($printQuery, $logQuery); // Let the original load method proceed with the modified query
    }
}
