<?php

namespace Gopersonal\Magento\Plugin;

class HardcodedProductPlugin
{
    public function afterFilter(
        \Magento\CatalogSearch\Model\Layer\Search\Plugin\CollectionFilter $subject,
        $result,
        \Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection $collection
    ) {
        $productId = 1556; // The product ID you want to hardcode
        $product = $collection->getResource()->getProductById($productId);

        if ($product) {
            $collection->clear(); // Clear existing results
            $collection->addItem($product); // Add your hardcoded product
        }

        return $result;
    }
}
