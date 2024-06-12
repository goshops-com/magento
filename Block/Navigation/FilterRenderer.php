<?php

namespace Gopersonal\Magento\Block\Navigation;

use Magento\Catalog\Model\Layer\Filter\FilterInterface;
use Magento\Framework\View\Element\Template;
use Magento\LayeredNavigation\Block\Navigation\FilterRendererInterface;

class FilterRenderer extends \Magento\LayeredNavigation\Block\Navigation\FilterRenderer
{
    public function render(FilterInterface $filter)
    {
        $this->assign('filterItems', $filter->getItems());
        $this->assign('filter', $filter); // Pass the filter object
        $html = $this->_toHtml();
        return $html;
    }

    public function getVersion()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');
        return $productMetadata->getVersion();
    }
}
