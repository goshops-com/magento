<?php
namespace Gopersonal\Magento\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class AfterOrderPlace implements ObserverInterface
{
    protected $customerSession;
    protected $curl;
    protected $logger;
    protected $scopeConfig;

    public function __construct(
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->customerSession = $customerSession;
        $this->curl = $curl;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
    }

    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $token = $this->customerSession->getData('gopersonal_jwt');
        if (!$token) {
            $this->logger->info('No API token found in session.');
            return;
        }

        $clientId = $this->scopeConfig->getValue('gopersonal/general/client_id', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $url = 'https://discover.gopersonal.ai/interaction/state/cart';  // Modified URL to include /state/cart endpoint

        if (strpos($clientId, 'D-') === 0) {
            $url = 'https://go-discover-dev.goshops.ai/interaction/state/cart';  // Development URL if client ID starts with 'D-'
        }

        // Setup headers and payload for the API request
        $this->curl->addHeader("Authorization", "Bearer " . $token);
        $this->curl->addHeader("Content-Type", "application/json");

        $postData = json_encode([
            'transactionId' => $order->getId(),
        ]);

        $this->curl->post($url, $postData);
        if ($this->curl->getStatus() != 200) {
            $this->logger->error('Purchase event API call failed.', ['status' => $this->curl->getStatus(), 'response' => $this->curl->getBody()]);
        }
    }
}
