<?php
namespace Gopersonal\Magento\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;

class AfterOrderPlace implements ObserverInterface
{
    protected $logger;
    protected $customerSession;
    protected $curl;
    protected $scopeConfig;
    protected $cookieManager;
    protected $cookieMetadataFactory;

    public function __construct(
        LoggerInterface $logger,
        CustomerSession $customerSession,
        Curl $curl,
        ScopeConfigInterface $scopeConfig,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory
    ) {
        $this->logger = $logger;
        $this->customerSession = $customerSession;
        $this->curl = $curl;
        $this->scopeConfig = $scopeConfig;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
    }

    public function execute(Observer $observer)
    {
        $orderIds = $observer->getEvent()->getOrderIds();
        if (empty($orderIds) || !is_array($orderIds)) {
            $this->logger->error('AfterOrderPlace: No order IDs found on thank you page.');
            return;
        }

        $orderId = reset($orderIds);
        $this->logger->info('AfterOrderPlace: Order ID on Thank You Page - ' . $orderId);

        $token = $this->cookieManager->getCookie('gopersonal_jwt');
        if (!$token) {
            $this->logger->error('AfterOrderPlace: No API token found in session.');
            return;
        }

        $clientId = $this->scopeConfig->getValue('gopersonal/general/client_id', ScopeInterface::SCOPE_STORE);
        $baseUrl = 'https://discover.gopersonal.ai/interaction/state/cart';
        $url = (strpos($clientId, 'D-') === 0) ? 'https://go-discover-dev.goshops.ai/interaction/state/cart' : $baseUrl;

        $this->curl->addHeader("Authorization", "Bearer " . $token);
        $this->curl->addHeader("Content-Type", "application/json");

        $postData = json_encode([
            'transactionId' => $orderId
        ]);

        $this->curl->post($url, $postData);
        if ($this->curl->getStatus() != 200) {
            $this->logger->error('AfterOrderPlace: Purchase event API call failed.', [
                'url' => $url,
                'status' => $this->curl->getStatus(),
                'response' => $this->curl->getBody(),
                'postData' => $postData
            ]);
        } else {
            $this->logger->info('AfterOrderPlace: Purchase event API call succeeded.', [
                'url' => $url,
                'status' => $this->curl->getStatus(),
                'response' => $this->curl->getBody(),
                'postData' => $postData
            ]);
        }
    }
}
