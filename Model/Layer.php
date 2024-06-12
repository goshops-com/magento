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

    // Define the attributes you want to use as filters (category and brand in this example)
    $filterableAttributes = ['category_id']; // Add your actual brand attribute code
    $logger->debug('Filterable attributes:', $filterableAttributes); 

    // Iterate over your manually specified filterable attributes
    foreach ($filterableAttributes as $attributeCode) {
        $filterCounts[$attributeCode] = [];

        // Get all possible options for the attribute
        $attribute = $collection->getResource()->getAttribute($attributeCode); // Get the attribute model
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
    }

    $logger->info('Finished filter count calculation');

    return $filterCounts;
}

}
