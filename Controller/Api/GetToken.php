<?php

namespace GoPersonal\Magento\Controller\Api;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Psr\Log\LoggerInterface;

class GetToken extends Action
{
    protected $customerSession;
    protected $resultJsonFactory;
    protected $cookieManager;
    protected $logger;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        JsonFactory $resultJsonFactory,
        CookieManagerInterface $cookieManager,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->customerSession = $customerSession;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->cookieManager = $cookieManager;
        $this->logger = $logger;
    }

    public function execute()
    {
        $requestId = uniqid('request_', true);
        
        $result = $this->resultJsonFactory->create();
        $readFromCookie = $this->getRequest()->getParam('readFromCookie') == 'true';

        // Retrieve the JWT token based on the presence of the query parameter
        $token = $this->cookieManager->getCookie('gopersonal_jwt');

        // Prepare data to return
        $data = [
            'token' => $token ? $token : 'No token is stored.',
            'readFrom' => $readFromCookie ? 'cookie' : 'session',
            'version' => '1.0.9'
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

    private function getStackTrace()
    {
        $e = new \Exception();
        return $e->getTraceAsString();
    }
}
