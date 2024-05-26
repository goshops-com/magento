<?php
namespace Gopersonal\Magento\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;

class SearchPlugin
{
    public function aroundLoad(Collection $subject, \Closure $proceed, $printQuery = false, $logQuery = false)
    {
        // Check if the current request is a search request
        $request = $subject->getResource()->getRequest();
        if ($request->getFullActionName() === 'catalogsearch_result_index') {
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
}
