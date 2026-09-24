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
        $response = $this->resultFactory->create(ResultFactory::TYPE_RAW);

        // Solo se acepta la llamada del script del modulo (console_log.phtml), que manda este header.
        // Se lee el header directo en vez de isAjax(), que tambien acepta el param isAjax.
        if ($this->getRequest()->getHeader('X-Requested-With') !== 'XMLHttpRequest') {
            $response->setHttpResponseCode(403);
            $response->setContents(__('Invalid request.'));
            return $response;
        }

        $token = $this->getRequest()->getParam('token');
        if ($token !== null && !$this->isValidToken($token)) {
            $response->setHttpResponseCode(400);
            $response->setContents(__('Invalid token.'));
            return $response;
        }

        if ($token !== null) {
            // Store token in session
            $this->customerSession->setData('gopersonal_jwt', $token);

            // Store token in a cookie
            $cookieMetadata = $this->cookieMetadataFactory->createPublicCookieMetadata()
                ->setDuration(60*60*24) // Cookie duration in seconds
                ->setPath('/')      // Specify the path where the cookie is available
                ->setDomain(null)   // Use current domain
                ->setSecure($this->getRequest()->isSecure()) // Secure en HTTPS, sin romper entornos HTTP
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

        $response->setContents($result);
        return $response;
    }

    /**
     * Formato de token aceptado (JWT/base64/base64url), con largo maximo.
     */
    private function isValidToken($token)
    {
        return is_string($token) && preg_match('#^[A-Za-z0-9._\-+/=]{1,4096}$#', $token) === 1;
    }
}
