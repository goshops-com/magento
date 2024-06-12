<?php
/**
 * @package   Gopersonal_Magento
 * @author    Shahid Taj
 */
namespace Gopersonal\Magento\Model;

use Magento\Framework\App\ObjectManager;
// use Magento\Catalog\Model\ResourceModel\Product\Collection;

class Layer extends \Magento\Catalog\Model\Layer
{
	public function getProductCollection()
	{
		$collection = parent::getProductCollection();
		//add custom filter
		$idArray = $this->getProductsIds();
		if (!empty($idArray)) {
            $collection->addAttributeToFilter('entity_id', ['in' => $idArray]);

            // Custom sorting based on array order (only for filtered products)
            $collection->getSelect()->order(
                new \Zend_Db_Expr('FIELD(e.entity_id, ' . implode(',', $idArray) . ')')
            );
        }

		// Calculate filter counts directly from the filtered collection
		$filterCounts = $this->calculateFilterCounts($collection);
		$this->setData('filter_counts', $filterCounts); // Store counts for later use
	
		return $collection;
	}

	public function getProductsIds()
	{
		 $objectManager = ObjectManager::getInstance();
		$helper = $objectManager->get(\Gopersonal\Magento\Helper\Data::class);

		return $helper->getProductsIds('layer');
	}

	private function calculateFilterCounts(\Magento\Catalog\Model\ResourceModel\Product\Collection $collection)
    {
        $filterCounts = [];

        // Iterate through filters in the current layer
        foreach ($this->getState()->getFilters() as $filter) {
            $attribute = $filter->getFilter()->getAttributeModel(); 
            $attributeCode = $attribute->getAttributeCode();
            $filterCounts[$attributeCode] = [];
            
            // Modify how you get the filter items
            $filterItems = $filter->getFilter()->getItems();
            foreach($filterItems as $filterItem){
                $value = $filterItem->getValue();
                // Use the filtered collection to count products with this value
                $filterCounts[$attributeCode][$value] = $collection->addFieldToFilter($attributeCode, $value)->getSize();
            }
        }

        return $filterCounts;
    }

}
