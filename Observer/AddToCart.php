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

class AddToCart implements ObserverInterface
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

            $token = $this->customerSession->getData('gopersonal_jwt');
            if (!$token) {
                $this->logger->info('No API token found in session.');
                return;
            }

            $clientId = $this->scopeConfig->getValue('gopersonal/general/client_id', ScopeInterface::SCOPE_STORE);
            $url = 'https://discover.gopersonal.ai/interaction';  // Default URL with /interaction

            if (strpos($clientId, 'D-') === 0) {
                $url = 'https://go-discover-dev.goshops.ai/interaction';  // Development URL if clientId starts with 'D-'
            }

            $this->curl->addHeader("Authorization", "Bearer " . $token);
            $this->curl->addHeader("Content-Type", "application/json");

            $postData = json_encode([
                'event' => 'cart',
                'item' => $productId,
                'quantity' => $quantity
            ]);

            $this->curl->post($url, $postData);
            if ($this->curl->getStatus() != 200) {
                $this->logger->error('AddToCart event: API call failed.', ['status' => $this->curl->getStatus(), 'response' => $this->curl->getBody()]);
                throw new LocalizedException(__('Failed to handle AddToCart event.'));
            }
        } catch (\Exception $e) {
            $this->logger->critical('AddToCart event: Exception occurred.', ['exception' => $e->getMessage()]);
            throw new LocalizedException(__('Error adding product to cart. Please contact support.'));
        }
    }
}
