<?php

namespace Gopersonal\Magento\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as SearchCollection;
use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;

class SearchOverride
{
    protected $resourceConnection;
    protected $productCollectionFactory;

    public function __construct(
        ResourceConnection $resourceConnection,
        ProductCollectionFactory $productCollectionFactory
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->productCollectionFactory = $productCollectionFactory;
    }

    public function aroundLoad(
        SearchCollection $subject,
        \Closure $proceed,
        $printQuery = false,
        $logQuery = false
    ) {
        $fixedProductIds = [1556]; // Your array of fixed product IDs (or fetch dynamically)

        // Call the original load method
        $result = $proceed($printQuery, $logQuery);

        // Check if the fixed product is already in the collection
        $existingProductIds = $subject->getColumnValues('entity_id');
        $missingProductIds = array_diff($fixedProductIds, $existingProductIds);

        if (!empty($missingProductIds)) {
            // Load the missing fixed products
            $productCollection = $this->productCollectionFactory->create();
            $productCollection->addAttributeToSelect('*')
                ->addFieldToFilter('entity_id', ['in' => $missingProductIds]);

            // Add the missing fixed products to the search collection
            foreach ($productCollection as $product) {
                $subject->addItem($product);
            }
        }

        return $result;
    }
}