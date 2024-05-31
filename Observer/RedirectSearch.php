<?php
/**
 * @package   Gopersonal_Search
 * @author    Shahid Taj
 */
namespace Gopersonal\Magento\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\UrlInterface;

class RedirectSearch implements ObserverInterface
{
    protected $actionFlag;
    protected $redirect;
    protected $url;

    public function __construct(
        ActionFlag $actionFlag,
        RedirectInterface $redirect,
        UrlInterface $url
    ) {
        $this->actionFlag = $actionFlag;
        $this->redirect = $redirect;
        $this->url = $url;
    }

    public function execute(Observer $observer)
    {
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
