<?php
namespace Gopersonal\Magento\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ThankYouPageObserver implements ObserverInterface
{
    protected $logger;
    protected $customerSession;
    protected $curl;
    protected $scopeConfig;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->logger = $logger;
        $this->customerSession = $customerSession;
        $this->curl = $curl;
        $this->scopeConfig = $scopeConfig;
    }

    public function execute(Observer $observer)
    {
        $orderIds = $observer->getEvent()->getOrderIds();  // array of order IDs
        if (empty($orderIds) || !is_array($orderIds)) {
            return;
        }

        $orderId = reset($orderIds);  // Get the first item from the array

        $this->logger->info('Order ID on Thank You Page: ' . $orderId);

        $token = $this->customerSession->getData('gopersonal_jwt');
        if (!$token) {
            $this->logger->info('No API token found in session.');
            return;
        }

        $clientId = $this->scopeConfig->getValue('gopersonal/general/client_id', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $url = 'https://discover.gopersonal.ai/interaction/state/cart';  // Default URL

        if (strpos($clientId, 'D-') === 0) {
            $url = 'https://go-discover-dev.goshops.ai/interaction/state/cart';  // Development URL if client ID starts with 'D-'
        }

        // Setup headers and payload for the API request
        $this->curl->addHeader("Authorization", "Bearer " . $token);
        $this->curl->addHeader("Content-Type", "application/json");

        $postData = json_encode([
            'transactionId' => $orderId
        ]);

        $this->curl->post($url, $postData);
        if ($this->curl->getStatus() != 200) {
            $this->logger->error('Purchase event API call failed.', ['status' => $this->curl->getStatus(), 'response' => $this->curl->getBody()]);
        }
    }
}
