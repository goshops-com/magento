<?php
namespace GoPersonal\Magento\Controller\Api;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\Result\JsonFactory;

class GetToken extends Action
{
    protected $customerSession;
    protected $resultJsonFactory;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->customerSession = $customerSession;
        $this->resultJsonFactory = $resultJsonFactory;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        // Retrieve the JWT token from the session
        $token = $this->customerSession->getData('gopersonal_jwt');

        // Prepare data to return
        $data = [
            'token' => $token ? $token : 'No token is stored.'
        ];

        // Check if the customer is logged in
        if ($this->customerSession->isLoggedIn()) {
            $data['customer_id'] = $this->customerSession->getCustomer()->getId();
            $data['customer_email'] = $this->customerSession->getCustomer()->getEmail();
        } else {
            $data['customer_id'] = null;
            $data['customer_email'] = null;
        }

        return $result->setData($data);
    }
}
