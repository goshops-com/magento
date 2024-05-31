<?php
/**
 * @package   Gopersonal_Magento
 * author    Shahid Taj
 */
namespace Gopersonal\Magento\Model;

use Gopersonal\Magento\Helper\Data;

class Layer extends \Magento\Catalog\Model\Layer
{
    protected $dataHelper;

    public function __construct(
        \Magento\Framework\App\ObjectManager $objectManager,
        Data $dataHelper
    ) {
        $this->dataHelper = $dataHelper;
        parent::__construct($objectManager);
    }

    public function getProductCollection()
    {
        $collection = parent::getProductCollection();
        // Add custom filter
        $idArray = $this->getProductsIds();
        if (!empty($idArray)) {
            $collection->addAttributeToFilter('entity_id', ["in" => $idArray]);
        }
        return $collection;
    }

    public function getProductsIds()
    {
        return $this->dataHelper->getProductsIds('layer');
    }
}
