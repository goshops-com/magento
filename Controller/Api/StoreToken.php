<?php
namespace GoPersonal\Magento\Controller\Api;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;

class StoreToken extends Action
{
    protected $customerSession;
    protected $cookieManager;
    protected $cookieMetadataFactory;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory
    ) {
        parent::__construct($context);
        $this->customerSession = $customerSession;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
    }

    public function execute()
    {
        $token = $this->getRequest()->getParam('token');
        if ($token !== null) {
            // Store token in session
            $this->customerSession->setData('gopersonal_jwt', $token);

            // Store token in a cookie
            $cookieMetadata = $this->cookieMetadataFactory->createPublicCookieMetadata()
                ->setDuration(3600) // Cookie duration in seconds
                ->setPath('/')      // Specify the path where the cookie is available
                ->setDomain(null)   // Use current domain
                ->setSecure(false)  // Set true if using HTTPS
                ->setHttpOnly(false); // Set true for HTTP only if you need to prevent JavaScript access

            $this->cookieManager->setPublicCookie(
                'gopersonal_jwt', // Cookie name
                $token,           // Cookie value
                $cookieMetadata
            );

            $result = __('Token stored successfully.');
        } else {
            $result = __('No token provided.');
        }

        $response = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $response->setContents($result);
        return $response;
    }
}
