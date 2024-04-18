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

class UpdateCart implements ObserverInterface
{
    protected $customerSession;
    protected $curl;
    protected $logger;
    protected $scopeConfig;

    public function __construct(
        Session $customerSession,
        Curl $curl,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->customerSession = $customerSession;
        $this->curl = $curl;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
    }

    public function execute(Observer $observer)
    {
        try {
            $info = $observer->getEvent()->getData('info');
            $cart = $observer->getEvent()->getCart();
            $items = $cart->getQuote()->getAllVisibleItems();  // Use getAllVisibleItems() to ignore child items of configurable products

            foreach ($items as $item) {
                $itemId = $item->getItemId();
                if (isset($info[$itemId]) && isset($info[$itemId]['qty'])) {
                    $newQuantity = $info[$itemId]['qty'];
                    $oldQuantity = $item->getQty();
                    
                    if ($newQuantity > $oldQuantity) {
                        $event = 'cart'; // Increasing the quantity
                    } elseif ($newQuantity < $oldQuantity) {
                        $event = 'remove-cart'; // Decreasing the quantity
                    } else {
                        continue; // No change in quantity
                    }

                    $productId = $item->getProductId();
                    $this->postUpdate($event, $productId, $newQuantity);
                }
            }
        } catch (\Exception $e) {
            $this->logger->critical('UpdateCart event: Exception occurred.', ['exception' => $e->getMessage()]);
            throw new LocalizedException(__('Error updating cart. Please contact support.'));
        }
    }

    private function postUpdate($event, $productId, $quantity)
    {
        $token = $this->customerSession->getData('gopersonal_jwt');
        if (!$token) {
            $this->logger->info('No API token found in session for product ID ' . $productId);
            return;
        }

        $clientId = $this->scopeConfig->getValue('gopersonal/general/client_id', ScopeInterface::SCOPE_STORE);
        $url = 'https://discover.gopersonal.ai/interaction';  // Default URL

        if (strpos($clientId, 'D-') === 0) {
            $url = 'https://go-discover-dev.goshops.ai/interaction';  // Development URL if clientId starts with 'D-'
        }

        $this->curl->addHeader("Authorization", "Bearer " . $token);
        $this->curl->addHeader("Content-Type", "application/json");

        $postData = json_encode([
            'event' => $event,
            'item' => $productId,
            'quantity' => $quantity
        ]);

        $this->curl->post($url, $postData);
        if ($this->curl->getStatus() != 200) {
            $this->logger->error('UpdateCart event for product ' . $productId . ': API call failed.', ['status' => $this->curl->getStatus(), 'response' => $this->curl->getBody()]);
        } else {
            $this->logger->info('UpdateCart event for product ' . $productId . ': Successfully posted.', ['status' => $this->curl->getStatus(), 'response' => $this->curl->getBody()]);
        }
    }
}
