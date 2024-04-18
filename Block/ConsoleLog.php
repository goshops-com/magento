<?php
namespace Gopersonal\Magento\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;

class ConsoleLog extends Template
{
    protected $_scopeConfig;

    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_scopeConfig = $context->getScopeConfig();
    }

    public function getClientId()
    {
        return $this->_scopeConfig->getValue(
            'gopersonal/general/client_id',
            ScopeInterface::SCOPE_STORE
        );
    }
}
