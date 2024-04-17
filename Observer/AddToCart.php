<?php
namespace GoPersonal\Magento\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class AddToCart implements ObserverInterface
{
    protected $customerSession;
    protected $curl;
    protected $logger;

    public function __construct(
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\HTTP\Client\Curl $curl,
        LoggerInterface $logger
    ) {
        $this->customerSession = $customerSession;
        $this->curl = $curl;
        $this->logger = $logger;
    }

    public function execute(Observer $observer)
    {
        $item = $observer->getEvent()->getData('quote_item');
        $product = $item->getProduct();
        $productId = $product->getId();
        $productSku = $product->getSku();

        $token = $this->customerSession->getData('api_token');
        
        if (!$token) {
            $this->logger->info('No API token found in session.');
            return;
        }

        $this->curl->addHeader("Authorization", "Bearer " . $token);
        $this->curl->addHeader("Content-Type", "application/json");
        
        $postData = json_encode([
            'productId' => $productId,
            'productSku' => $productSku
        ]);

        try {
            $this->curl->post('https://worker-small-frog-11cb.go-shops.workers.dev/', $postData);
            if ($this->curl->getStatus() == 200) {
                $this->logger->info('AddToCart event: Data posted successfully.', ['response' => $this->curl->getBody()]);
            } else {
                $this->logger->error('AddToCart event: API call failed.', ['status' => $this->curl->getStatus(), 'response' => $this->curl->getBody()]);
            }
        } catch (\Exception $e) {
            $this->logger->error('AddToCart event: Exception occurred.', ['exception' => $e->getMessage()]);
        }
    }
}
