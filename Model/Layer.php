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
		$logger = \Magento\Framework\App\ObjectManager::getInstance()->get(\Psr\Log\LoggerInterface::class);

		$logger->info('Starting filter count calculation'); 
		
		foreach ($this->getState()->getFilters() as $filter) {
			$attribute = $filter->getFilter()->getAttributeModel();
			$attributeCode = $attribute->getAttributeCode();
			$logger->debug('Calculating counts for attribute: ' . $attributeCode); 

			$filterCounts[$attributeCode] = [];

			// Get all possible filter options (not just applied ones)
			$allFilterOptions = $attribute->getSource()->getAllOptions();
			
			// Initialize counts for all options
			foreach ($allFilterOptions as $option) {
				$filterCounts[$attributeCode][$option['value']] = 0; 
			}

			// Iterate over filter items and update counts from filtered collection
			$filterItems = $filter->getFilter()->getItems() ?: []; // Default to empty array if null

			foreach ($filterItems as $filterItem) {
				$value = $filterItem->getValue();
				$count = $collection->addFieldToFilter($attributeCode, $value)->getSize();
				$filterCounts[$attributeCode][$value] = $count;
				$logger->debug('Filter item "' . $filterItem->getLabel() . '" (' . $value . ') has count: ' . $count);
			}

			// If an option doesn't have a product, we remove it from filterCounts
			foreach ($filterCounts[$attributeCode] as $optionKey => $optionValue) {
			if ($optionValue == 0){
				unset($filterCounts[$attributeCode][$optionKey]);
				$logger->debug('Removing filter item with no products: ' . $optionKey);
			}
			}
		}

		$logger->info('Finished filter count calculation');

		return $filterCounts;
	}

}
