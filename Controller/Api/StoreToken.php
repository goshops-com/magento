<?php
namespace GoPersonal\Magento\Controller\Api;

class StoreToken extends \Magento\Framework\App\Action\Action
{
    protected $customerSession;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession
    ) {
        $this->customerSession = $customerSession;
        parent::__construct($context);
    }

    public function execute()
    {
        $token = $this->getRequest()->getParam('token');
        if ($token !== null) {
            $this->customerSession->setData('api_token', $token);
            $result = __('Token stored successfully.');
        } else {
            $result = __('No token provided.');
        }

        $response = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);
        $response->setContents($result);
        return $response;
    }

    public function getToken()
    {
        $token = $this->customerSession->getData('api_token');
        if ($token) {
            $result = __('Stored Token: %1', $token);
        } else {
            $result = __('No token is stored.');
        }

        $response = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);
        $response->setContents($result);
        return $response;
    }
}
