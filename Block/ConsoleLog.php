<?php
namespace Gopersonal\Magento\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Registry;
use Magento\Checkout\Model\Session as CheckoutSession;  // Correctly use CheckoutSession to access cart items
use Magento\Store\Model\ScopeInterface;

class ConsoleLog extends Template
{
    protected $_request;
    protected $_registry;
    protected $_scopeConfig;  // ScopeConfig to access configuration values
    protected $_checkoutSession;  // Add property for checkout session

    public function __construct(
        Context $context,
        Http $request,
        Registry $registry,
        CheckoutSession $checkoutSession,  // Inject CheckoutSession
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_request = $request;
        $this->_registry = $registry;
        $this->_scopeConfig = $context->getScopeConfig();
        $this->_checkoutSession = $checkoutSession;  // Initialize CheckoutSession
    }

    public function isHomePage()
    {
        return $this->_request->getFullActionName() == 'cms_index_index';
    }

    public function isProductPage()
    {
        return $this->_request->getFullActionName() == 'catalog_product_view';
    }

    public function isCartPage()
    {
        return $this->_request->getFullActionName() == 'checkout_cart_index';
    }

    public function isCheckoutPage()
    {
        return $this->_request->getFullActionName() == 'checkout_index_index';
    }

    public function getCurrentProductId()
    {
        if ($this->isProductPage()) {
            return $this->_registry->registry('current_product') ? $this->_registry->registry('current_product')->getId() : null;
        }
        return null;
    }

    public function getClientId()
    {
        return $this->_scopeConfig->getValue(
            'gopersonal/general/client_id',
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getCartItems()
    {
        $items = [];
        foreach ($this->_checkoutSession->getQuote()->getAllVisibleItems() as $item) {
            $items[] = [
                'product_id' => $item->getProduct()->getSku(),
                'quantity' => $item->getQty()
            ];
        }
        return $items;
    }
}
