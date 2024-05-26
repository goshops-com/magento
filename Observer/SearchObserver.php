<?php
namespace Gopersonal\Magento\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\RequestInterface;

class SearchObserver implements ObserverInterface
{
    protected $productCollectionFactory;
    protected $request;

    public function __construct(
        ProductCollectionFactory $productCollectionFactory,
        RequestInterface $request
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->request = $request;
    }

    public function execute(Observer $observer)
    {
        $fullActionName = $this->request->getFullActionName();
        $query = $this->request->getParam('q');
        
        if ($fullActionName === 'catalogsearch_result_index' && !empty($query)) {
            $collection = $observer->getEvent()->getCollection();
            
            // Clear the existing collection items
            $collection->clear();
            
            // Load the product with ID 1556
            $productCollection = $this->productCollectionFactory->create();
            $productCollection->addFieldToFilter('entity_id', 1556);

            // Add the product to the collection
            foreach ($productCollection as $product) {
                $collection->addItem($product);
            }

            // Ensure only the specified product is returned
            $collection->getSelect()->reset(\Magento\Framework\DB\Select::WHERE);
            $collection->getSelect()->where('e.entity_id = ?', 1556);
        }
    }
}
