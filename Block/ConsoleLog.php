<?php
namespace Gopersonal\Magento\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Registry;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Store\Model\ScopeInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableTypeResource;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;

class ConsoleLog extends Template
{
    protected $_request;
    protected $_registry;
    protected $_scopeConfig;
    protected $_checkoutSession;
    protected $_customerSession;
    protected $_configurableTypeResource;
    protected $_layerResolver;

    public function __construct(
        Context $context,
        Http $request,
        Registry $registry,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        ConfigurableTypeResource $configurableTypeResource,
        LayerResolver $layerResolver,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_request = $request;
        $this->_registry = $registry;
        $this->_scopeConfig = $context->getScopeConfig();
        $this->_checkoutSession = $checkoutSession;
        $this->_customerSession = $customerSession;
        $this->_configurableTypeResource = $configurableTypeResource;
        $this->_layerResolver = $layerResolver;
    }

    public function isHomePage() {
        return $this->_request->getFullActionName() == 'cms_index_index';
    }

    public function isProductPage() {
        return $this->_request->getFullActionName() == 'catalog_product_view';
    }

    public function isCartPage() {
        return $this->_request->getFullActionName() == 'checkout_cart_index';
    }

    public function isCheckoutPage() {
        return $this->_request->getFullActionName() == 'checkout_index_index';
    }

    public function getCurrentProduct() {
        return $this->isProductPage() ? $this->_registry->registry('current_product') : null;
    }

    public function isSearchResultsPage() {
        return $this->_request->getFullActionName() == 'catalogsearch_result_index' || $this->_request->getFullActionName() == 'search_index_index';
    }

    public function isSearchResultsEmptyPage() {
        return $this->_request->getFullActionName() == 'searchresult_index_index';
    }

    public function isCategoryPage() {
        return $this->_request->getFullActionName() == 'catalog_category_view';
    }

    public function getCategoryId() {
        if ($this->isCategoryPage()) {
            return $this->_layerResolver->get()->getCurrentCategory()->getId();
        }
        return null;
    }

    public function getCurrentProductId() {
        $product = $this->getCurrentProduct();
        if ($product) {
            if ($product->getTypeId() == 'simple') {
                $parentIds = $this->_configurableTypeResource->getParentIdsByChild($product->getId());
                if (!empty($parentIds)) {
                    return $parentIds[0];  // Return the first parent ID (configurable product)
                }
            }
            return $product->getId();  // Return the ID of the product if not simple or no parents found
        }
        return null;
    }

    public function getCurrentProductType() {
        $product = $this->getCurrentProduct();
        return $product ? $product->getTypeId() : null;
    }

    public function getClientId() {
        return $this->_scopeConfig->getValue('gopersonal/general/client_id', ScopeInterface::SCOPE_STORE);
    }

    public function getCartItems() {
        $items = [];
        foreach ($this->_checkoutSession->getQuote()->getAllVisibleItems() as $item) {
            $items[] = [
                'product_id' => $item->getProduct()->getId(),
                'quantity' => $item->getQty()
            ];
        }
        return $items;
    }

    public function isLoggedIn() {
        return $this->_customerSession->isLoggedIn();
    }

    public function getCustomerId() {
        return $this->_customerSession->getCustomer()->getId();
    }

    public function getCustomerEmail() {
        return $this->_customerSession->getCustomer()->getEmail();
    }
}
