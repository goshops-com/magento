<?php
/**
 * @package   Gopersonal_Magento
 * @author    Shahid Taj
 */
namespace Gopersonal\Magento\Model\ResourceModel;

class Category extends \Magento\Catalog\Model\ResourceModel\Category
{
    public function getProductCount($category)
    {
        $collection = $category->getProductCollection();
        //add custom filter
		$idArray = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];
		if (!empty($idArray)) {
            $collection->addAttributeToFilter('entity_id', ["in"=>$idArray]);
        }
        return intval($collection->count());
    }
}