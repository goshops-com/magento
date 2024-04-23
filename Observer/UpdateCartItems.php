<?php
namespace Gopersonal\Magento\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableProductResource;
use Magento\Customer\Model\Session;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class UpdateCartItems implements ObserverInterface
{
    protected $configurableProductResource;
    protected $customerSession;
    protected $curl;
    protected $logger;
    protected $scopeConfig;

    public function __construct(
        ConfigurableProductResource $configurableProductResource,
        Session $customerSession,
        Curl $curl,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->configurableProductResource = $configurableProductResource;
        $this->customerSession = $customerSession;
        $this->curl = $curl;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
    }

    public function execute(Observer $observer)
    {
        $info = $observer->getEvent()->getData('info');
        $cart = $observer->getEvent()->getCart();

        $this->logger->info('UpdateCartItems observer executed');
        
        $token = $this->customerSession->getData('gopersonal_jwt');
        if (!$token) {
            $this->logger->info('No API token found in session.');
            return;
        }

        $clientId = $this->scopeConfig->getValue('gopersonal/general/client_id', ScopeInterface::SCOPE_STORE);
        $url = 'https://discover.gopersonal.ai/interaction';  // Default URL

        if (strpos($clientId, 'D-') === 0) {
            $url = 'https://go-discover-dev.goshops.ai/interaction';  // Development URL if client ID starts with 'D-'
        }

        foreach ($info as $itemId => $itemInfo) {
            $qty = isset($itemInfo['qty']) ? (int) $itemInfo['qty'] : 0;
            if ($qty > 0) {
                $cartItem = $cart->getQuote()->getItemById($itemId);
                if ($cartItem) {
                    $product = $cartItem->getProduct();
                    $oldQty = $cartItem->getQty();
                    $qtyDiff = $qty - $oldQty; // Calculate the difference in quantity
                    $changeType = ($qtyDiff > 0) ? 'increase' : 'decrease'; // Determine if the change was an increase or decrease

                    // Obtain the parent product ID if this is a simple product part of a configurable product
                    if ($product->getTypeId() == 'simple') {
                        $parentIds = $this->configurableProductResource->getParentIdsByChild($product->getId());
                        $productId = (!empty($parentIds)) ? $parentIds[0] : $product->getId();
                    } else {
                        $productId = $product->getId();
                    }

                    // Setup headers and payload for the API request
                    $this->curl->addHeader("Authorization", "Bearer " . $token);
                    $this->curl->addHeader("Content-Type", "application/json");

                    $postData = json_encode([
                        'event' => ($changeType == 'increase') ? 'cart' : 'remove-cart',
                        'item' => $productId,
                        'quantity' => abs($qtyDiff)
                    ]);

                    $this->curl->post($url, $postData);
                    if ($this->curl->getStatus() != 200) {
                        $this->logger->error('API call failed.', ['status' => $this->curl->getStatus(), 'response' => $this->curl->getBody()]);
                    }
                }
            }
        }

        return $this;
    }
}
