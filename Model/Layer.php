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

		$idArray = $this->getProductsIds();
		if (!empty($idArray)) {
			$collection->addAttributeToFilter('entity_id', ['in' => $idArray]);

			// Custom sorting based on array order 
			$collection->getSelect()->order(
				new \Zend_Db_Expr('FIELD(e.entity_id, ' . implode(',', $idArray) . ')')
			);
		}

		$filterCounts = $this->calculateFilterCounts($collection);
		$this->setData('filter_counts', $filterCounts);

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
		$logger = ObjectManager::getInstance()->get(\Psr\Log\LoggerInterface::class);

		$logger->info('Starting filter count calculation');

		// Fetch filterable attributes dynamically
		$filterableAttributes = ObjectManager::getInstance()
			->create(\Magento\Catalog\Model\Layer\Category\FilterableAttributeList::class)
			->getList();

		// Iterate over filterable attributes 
		foreach ($filterableAttributes as $attribute) {
			$attributeCode = $attribute->getAttributeCode();

			// Skip the price attribute
			if ($attributeCode == 'price') {
				continue; // Move to the next attribute
			}

			$filterCounts[$attributeCode] = [];

			// Get the attribute model (if it exists)
			$attribute = $collection->getResource()->getAttribute($attributeCode);

			// Check if the attribute exists and has options
			if ($attribute && $attribute->usesSource()) {
				// Get all possible options for the attribute
				$attributeOptions = $attribute->getSource()->getAllOptions();

				// Filter collection to include only products with attribute values
				$collection->addAttributeToFilter($attributeCode, ['notnull' => true]);

				// Iterate through each product to collect filter data
				foreach ($collection as $product) {
					$productAttributeValue = $product->getData($attributeCode);

					// Find the matching filter option label and ensure it's counted
					foreach ($attributeOptions as $option) {
						if ($option['value'] == $productAttributeValue) {
							if (!isset($filterCounts[$attributeCode][$option['value']])) {
								$filterCounts[$attributeCode][$option['value']] = 0;
							}
							$filterCounts[$attributeCode][$option['value']]++;
							$logger->debug('Filter item "' . $option['label'] . '" (' . $option['value'] . ') has count: ' . $filterCounts[$attributeCode][$option['value']]);
						}
					}
				}

				// Remove filter options with zero count
				foreach ($filterCounts[$attributeCode] as $optionKey => $optionValue) {
					if ($optionValue == 0) {
						unset($filterCounts[$attributeCode][$optionKey]);
					}
				}
			} else {
				$logger->warning("Attribute '$attributeCode' not found or doesn't use a source model");
			}
		}

		$logger->info('Finished filter count calculation');

		return $filterCounts;
	}

}
