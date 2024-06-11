<?php

namespace Gopersonal\Magento\Block\Navigation;

use Magento\Catalog\Model\Layer\Filter\FilterInterface;
use Magento\Framework\View\Element\Template;
use Magento\LayeredNavigation\Block\Navigation\FilterRendererInterface;

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
        // Custom logic can be added here
        $this->assign('filterItems', $filter->getItems());
        $html = $this->_toHtml();
        $this->assign('filterItems', []);
        return $html;
    }

    public function getVersion()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');
        return $productMetadata->getVersion();
    }
}
