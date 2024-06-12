<?php

namespace Gopersonal\Magento\Block\Navigation;

use Magento\Catalog\Model\Layer\Filter\FilterInterface;
use Magento\Framework\View\Element\Template;
use Magento\LayeredNavigation\Block\Navigation\FilterRendererInterface;
use Magento\Framework\DataObject;

/**
 * Custom Catalog layer filter renderer
 */
class FilterRenderer extends \Magento\LayeredNavigation\Block\Navigation\FilterRenderer
{
    /**
     * Render filter
     *
     * @param FilterInterface $filter
     * @return string
     */
    public function render(FilterInterface $filter)
    {
        $filterItems = $this->_buildFilterItems($filter->getItems(), $filter);
        $this->assign('filterItems', $filterItems);
        $html = $this->_toHtml();
        $this->assign('filterItems', []);
        return $html;
    }

    /**
     * Build filter items manually
     *
     * @param array $filterItems
     * @param FilterInterface $filter
     * @return array
     */
    protected function _buildFilterItems($filterItems, $filter)
    {
        $result = [];
        foreach ($filterItems as $item) {
            if (is_object($item)) {
                $result[] = new DataObject([
                    'count' => $item->getCount(),
                    'label' => $item->getLabel(),
                    'url' => $item->getUrl(),
                ]);
            }
        }
        return $result;
    }

    public function getVersion()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');
        return $productMetadata->getVersion();
    }
}
