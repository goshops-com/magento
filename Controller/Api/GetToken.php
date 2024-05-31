<?php

namespace GoPersonal\Magento\Controller\Api;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Module\ModuleListInterface;

class GetToken extends Action
{
    protected $customerSession;
    protected $resultJsonFactory;
    protected $cookieManager;
    protected $moduleList;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        JsonFactory $resultJsonFactory,
        CookieManagerInterface $cookieManager,
        ModuleListInterface $moduleList
    ) {
        parent::__construct($context);
        $this->customerSession = $customerSession;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->cookieManager = $cookieManager;
        $this->moduleList = $moduleList;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        // Check if 'readFromCookie' parameter is set to true
        $readFromCookie = $this->getRequest()->getParam('readFromCookie') == 'true';

        // Retrieve the JWT token based on the presence of the query parameter
        $token = $this->cookieManager->getCookie('gopersonal_jwt');

        // Prepare data to return
        $data = [
            'token' => $token ? $token : 'No token is stored.',
            'readFrom' => $readFromCookie ? 'cookie' : 'session'
        ];

        // Check if the customer is logged in
        if ($this->customerSession->isLoggedIn()) {
            $data['customer_id'] = $this->customerSession->getCustomer()->getId();
            $data['customer_email'] = $this->customerSession->getCustomer()->getEmail();
        } else {
            $data['customer_id'] = null;
            $data['customer_email'] = null;
        }

        // Retrieve module version
        $moduleVersion = $this->moduleList->getOne('Gopersonal_Magento')['setup_version'];

        // Set the module version in the header
        $result->getHeaders()->addHeaderLine('X-Gopersonal-Version', $moduleVersion);

        return $result->setData($data);
    }
}
