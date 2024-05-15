<?php
namespace Gopersonal\Magento\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\App\Request\Http as HttpRequest;

class UrlRewrite implements ObserverInterface
{
    protected $request;

    public function __construct(HttpRequest $request)
    {
        $this->request = $request;
    }

    public function execute(Observer $observer)
    {
        $originalPath = $this->request->getOriginalPathInfo();
        if ($originalPath === '/gp-firebase.js') {
            $this->request->setPathInfo('/gp-firebase-js/index/index');
        }
    }
}
