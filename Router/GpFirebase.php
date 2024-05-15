<?php
namespace GoPersonal\Magento\Router;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\UrlInterface;

class GpFirebase implements RouterInterface
{
    protected $actionFactory;
    protected $url;

    public function __construct(
        ActionFactory $actionFactory,
        UrlInterface $url
    ) {
        $this->actionFactory = $actionFactory;
        $this->url = $url;
    }

    public function match(RequestInterface $request)
    {
        $identifier = trim($request->getPathInfo(), '/');
        if ($identifier == 'gp-firebase.js') {
            $request->setModuleName('gopersonal_magento')
                ->setControllerName('index')
                ->setActionName('index');

            return $this->actionFactory->create(
                \Magento\Framework\App\Action\Forward::class,
                ['request' => $request]
            );
        }

        return null;
    }
}