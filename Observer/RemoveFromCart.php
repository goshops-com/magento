<?php
namespace GoPersonal\Magento\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class RemoveFromCart implements ObserverInterface
{
    protected $customerSession;
    protected $curl;

    public function __construct(
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\HTTP\Client\Curl $curl
    ) {
        $this->customerSession = $customerSession;
        $this->curl = $curl;
    }

    public function execute(Observer $observer)
    {
        $token = $this->customerSession->getData('api_token');
        if ($token) {
            $this->curl->addHeader("Authorization", "Bearer " . $token);
            $this->curl->post('https://your.api.endpoint/removefromcart', json_encode(['data' => 'your_data_here']));
        }
    }
}
