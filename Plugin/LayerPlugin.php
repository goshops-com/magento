<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\DB\Select;

class LayerPlugin
{
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     */
    public function __construct(
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
    }

    /**
     * Plugin to modify the product collection before getting aggregations
     *
     * @param Layer $subject
     * @param Collection $collection
     * @return Collection
     */
    public function beforeGetProductCollection(Layer $subject)
    {
        $collection = $this->productCollectionFactory->create();
        
        // Add your two hardcoded product IDs here
        $productIds = [1, 2]; // Replace with your actual product IDs
        
        $collection->addAttributeToSelect('*')
            ->addAttributeToFilter('entity_id', ['in' => $productIds]);

        // Clone the filters from the original collection to maintain the layered navigation
        $originalCollection = $subject->getProductCollection();
        if ($originalCollection) {
            foreach ($originalCollection->getFilters() as $filter) {
                $collection->addFilter($filter);
            }
        }

        // Set the modified collection back to the layer
        $subject->setProductCollection($collection);

        return $collection;
    }

    /**
     * After plugin to ensure we're using our modified collection for aggregations
     *
     * @param Layer $subject
     * @param Collection $result
     * @return Collection
     */
    public function afterGetProductCollection(Layer $subject, $result)
    {
        return $result;
    }
}