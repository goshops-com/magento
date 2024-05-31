<?php
/**
 * @package   Gopersonal_Search
 * @author    Shahid Taj
 */
namespace Gopersonal\Search\Model;

use Magento\Framework\App\ObjectManager;

class Layer extends \Magento\Catalog\Model\Layer
{
	public function getProductCollection()
	{
		$collection = parent::getProductCollection();
		//add custom filter
		$idArray = $this->getProductsIds();
		if (!empty($idArray)) {
            $collection->addAttributeToFilter('entity_id', ["in"=>$idArray]);
        }
		return $collection;
	}

	public function getProductsIds()
	{
		 $objectManager = ObjectManager::getInstance();
		$helper = $objectManager->get(\Gopersonal\Search\Helper\Data::class);

		return $helper->getProductsIds();
	}
}
