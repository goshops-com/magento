<?php

namespace Gopersonal\Magento\Plugin;

class SearchAdapterPlugin
{
    public function beforeProcess(
        \Magento\CatalogSearch\Model\Adapter\Mysql\Filter\Preprocessor $subject,
        $query
    ) {
        $productId = 1556;
        $query->setQueryText('product:' . $productId); // Force the query to search for this product ID
        return [$query]; 
    }
}