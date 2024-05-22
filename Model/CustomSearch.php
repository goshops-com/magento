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
        // Return an array with some key information to prevent TypeError
        return [
            'RESULT_BLOCK',
            $this->getRequest()->getParam('q'), // Search query parameter
            $this->getRequest()->getParam('p')  // Page parameter
        ];
    }
}
