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

class RemoveFromCart implements ObserverInterface
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
            $item = $observer->getEvent()->getData('quote_item');
            $product = $item ? $item->getProduct() : null;
            $productId = $product ? $product->getId() : 'Product ID not found';
            $quantity = $item ? $item->getQty() : 'Quantity not found';

            $token = $this->customerSession->getData('api_token');
            if (!$token) {
                $this->logger->info('No API token found in session.');
                return;
            }

            $clientId = $this->scopeConfig->getValue('gopersonal/general/client_id', ScopeInterface::SCOPE_STORE);
            $url = 'https://discover.gopersonal.ai/interaction';

            if (strpos($clientId, 'D-') === 0) {
                $url = 'https://go-discover-dev.goshops.ai/interaction';
            }

            $this->curl->addHeader("Authorization", "Bearer " . $token);
            $this->curl->addHeader("Content-Type", "application/json");

            $postData = json_encode([
                'event' => 'remove-cart',
                'item' => $productId,
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
