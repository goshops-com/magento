<?php
namespace Gopersonal\Magento\Model\ResourceModel\Layer\Filter;

use Gopersonal\Magento\Helper\Data;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;

class PriceFilter extends \Magento\Catalog\Model\ResourceModel\Layer\Filter\Price
{
    /**
     * @var \Magento\Catalog\Model\Layer
     */
    private $layer;

    /**
     * @var \Magento\Customer\Model\Session
     */
    private $session;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    protected $helper;

    /**
     * @param \Magento\Framework\Model\ResourceModel\Db\Context $context
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param LayerResolver $layerResolver
     * @param \Magento\Customer\Model\Session $session
     * @param Data $helper
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param null $connectionName
     */
    public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        LayerResolver $layerResolver,
        \Magento\Customer\Model\Session $session,
        Data $helper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        $connectionName = null
    ) {
        $this->layer = $layerResolver->get(); // Resolve the custom layer
        $this->session = $session;
        $this->helper = $helper;
        $this->storeManager = $storeManager;
        parent::__construct(
            $context,
            $eventManager,
            $layerResolver, // Correctly pass LayerResolver
            $session,
            $storeManager,
            $connectionName
        );
    }

    /**
     * Get count for price range
     *
     * @param float $range
     * @return array
     */
    public function getCount($range)
    {
        $productCollection = $this->layer->getProductCollection();
        $priceCounts = $this->_buildPriceCounts($productCollection, $range);
        return $priceCounts;
    }

    /**
     * Build price counts manually
     *
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $productCollection
     * @param float $range
     * @return array
     */
    protected function _buildPriceCounts($productCollection, $range)
    {
        $priceCounts = [];
        foreach ($productCollection as $product) {
            $price = $product->getFinalPrice();
            $rangeIndex = floor($price / $range) + 1;
            if (!isset($priceCounts[$rangeIndex])) {
                $priceCounts[$rangeIndex] = 0;
            }
            $priceCounts[$rangeIndex]++;
        }
        return $priceCounts;
    }

    public function getSelect()
    {
        $collection = $this->layer->getProductCollection();
        // add custom filter

        $idArray = $this->helper->getProductsIds('price');
        if (!empty($idArray)) {
            $collection->addAttributeToFilter('entity_id', ["in"=>$idArray]);
        }
        $collection->addPriceData(
            $this->session->getCustomerGroupId(),
            $this->storeManager->getStore()->getWebsiteId()
        );

        return $collection->getSelect();
    }
}
