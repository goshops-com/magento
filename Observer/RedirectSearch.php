<?php
/**
 * @package   Gopersonal_Magento
 * @author    Shahid Taj
 */
namespace Gopersonal\Magento\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;

class RedirectSearch implements ObserverInterface
{
    protected $actionFlag;
    protected $redirect;
    protected $url;
    protected $scopeConfig;
    protected $cookieManager;

    public function __construct(
        ActionFlag $actionFlag,
        RedirectInterface $redirect,
        UrlInterface $url,
        ScopeConfigInterface $scopeConfig,
        CookieManagerInterface $cookieManager
    ) {
        $this->actionFlag = $actionFlag;
        $this->redirect = $redirect;
        $this->url = $url;
        $this->scopeConfig = $scopeConfig;
        $this->cookieManager = $cookieManager;
    }

    public function execute(Observer $observer)
    {
        // Check if the feature is enabled
        $isEnabled = $this->scopeConfig->getValue(
            'gopersonal/general/gopersonal_has_search',
            ScopeInterface::SCOPE_STORE
        );

        if ($isEnabled !== 'YES') {
            // Skip the redirect if the feature is not enabled
            return;
        }

        // Check if the JWT token is present
        $token = $this->cookieManager->getCookie('gopersonal_jwt');
        if (!$token) {
            // Skip the redirect if the token is not present
            return;
        }

        $controller = $observer->getControllerAction();
        $request = $controller->getRequest();
        $queryParams = $request->getParams();

        $this->actionFlag->set('', \Magento\Framework\App\Action\Action::FLAG_NO_DISPATCH, true);

        // Define your custom URL or route here
        $customUrl = $this->url->getUrl('search', ['_query' => $queryParams]);

        // Redirect to the custom URL
        $controller->getResponse()->setRedirect($customUrl);
    }
}
