<?php

namespace Gopersonal\Magento\Block\LayeredNavigation;

use Amasty\Shopby\Block\Navigation\SwatchRenderer as AmastyRenderLayered;

/**
 * Custom RenderLayered class
 */
class RenderLayered extends AmastyRenderLayered
{
     /**
     * Path to template file.
     *
     * @var string
     */
    // protected $_template = 'Magento_Swatches::product/layered/renderer.phtml';
    protected $_template = 'Gopersonal_Magento::layer/filter/swatch/default.phtml';
}
