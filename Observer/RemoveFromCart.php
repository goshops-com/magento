<?php
namespace Gopersonal\Magento\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableProductResource;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;

class RemoveFromCart implements ObserverInterface
{
    protected $customerSession;
    protected $curl;
    protected $logger;
    protected $scopeConfig;
    protected $configurableProductResource;
    protected $cookieManager;
    protected $cookieMetadataFactory;

    public function __construct(
        Session $customerSession,
        Curl $curl,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig,
        ConfigurableProductResource $configurableProductResource,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory
    ) {
        $this->customerSession = $customerSession;
        $this->curl = $curl;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->configurableProductResource = $configurableProductResource;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
    }

    public function execute(Observer $observer)
    {
        try {
            $item = $observer->getEvent()->getData('quote_item');
            $product = $item ? $item->getProduct() : null;
            $productId = $product ? $product->getId() : 'Product ID not found';
            $quantity = $item ? $item->getQty() : 'Quantity not found';

            // Check if product is a simple product and part of a configurable product
            if ($product && $product->getTypeId() == 'simple') {
                $parentIds = $this->configurableProductResource->getParentIdsByChild($productId);
                if (!empty($parentIds)) {
                    $productId = $parentIds[0];  // Use the parent configurable product ID
                }
            }

            $token = $this->cookieManager->getCookie('gopersonal_jwt');
            if (!$token) {
                $this->logger->info('No API token found in session.');
                return;
            }

            $clientId = $this->scopeConfig->getValue('gopersonal/general/client_id', ScopeInterface::SCOPE_STORE);
            $url = 'https://discover.gopersonal.ai/interaction';  // Default URL

            if (strpos($clientId, 'D-') === 0) {
                $url = 'https://go-discover-dev.goshops.ai/interaction';  // Development URL if client ID starts with 'D-'
            }

            $this->curl->addHeader("Authorization", "Bearer " . $token);
            $this->curl->addHeader("Content-Type", "application/json");

            $postData = json_encode([
                'event' => 'remove-cart',
                'item' => $productId,  // Configurable product ID if applicable
                'quantity' => $quantity
            ]);

            $this->curl->post($url, $postData);
            if ($this->curl->getStatus() != 200) {
                $this->logger->error('RemoveFromCart event: API call failed.', ['status' => $this->curl->getStatus(), 'response' => $this->curl->getBody()]);
                throw new LocalizedException(__('Failed to handle RemoveFromCart event.'));
            }
        } catch (\Exception $e) {
            $this->logger->critical('RemoveFromCart event: Exception occurred.', ['exception' => $e->getMessage()]);
            throw new LocalizedException(__('Error removing product from cart. Please contact support.'));
        }
    }
}
