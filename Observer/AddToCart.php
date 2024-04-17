<?php
namespace Gopersonal\Magento\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

class AddToCart implements ObserverInterface
{
    protected $customerSession;
    protected $curl;
    protected $logger;

    public function __construct(
        Session $customerSession,
        Curl $curl,
        LoggerInterface $logger
    ) {

        $logger->debug("AddToCart Observer Instantiated");

        $this->customerSession = $customerSession;
        $this->curl = $curl;
        $this->logger = $logger;
    }

    public function execute(Observer $observer)
    {
        try {
            $item = $observer->getEvent()->getData('quote_item');
            $product = $item ? $item->getProduct() : null;
            $productId = $product ? $product->getId() : 'Product ID not found';
            $productSku = $product ? $product->getSku() : 'SKU not found';

            $token = $this->customerSession->getData('api_token');
            
            if (!$token) {
                $this->logger->info('No API token found in session.');
                return;
            }

            $this->curl->addHeader("Authorization", "Bearer " . $token);
            $this->curl->addHeader("Content-Type", "application/json");
            
            $postData = json_encode([
                'productId' => $productId,
                'productSku' => $productSku,
                'token' => $token
            ]);

            $this->curl->post('https://worker-small-frog-11cb.go-shops.workers.dev/', $postData);
            if ($this->curl->getStatus() != 200) {
                $this->logger->error('AddToCart event: API call failed.', ['status' => $this->curl->getStatus(), 'response' => $this->curl->getBody()]);
                throw new LocalizedException(__('Failed to handle AddToCart event.'));
            }
        } catch (\Exception $e) {
            $this->logger->error('AddToCart event: Exception occurred.', ['exception' => $e->getMessage()]);
            throw new LocalizedException(__('Error adding product to cart.'));
        }
    }

}
