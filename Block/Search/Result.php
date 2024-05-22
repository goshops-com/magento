<?php
namespace Gopersonal\Magento\Block\Search;

use Magento\Framework\View\Element\Template;

class Result extends \Magento\Framework\View\Element\Template
{
    protected function _construct()
    {
        parent::_construct();
        // Disable cache for this block
        $this->setCacheLifetime(0);
    }

    public function getCacheKeyInfo()
    {
        // Return an empty array to effectively disable caching
        return [];
    }
}
