<?php
namespace GoPersonal\Magento\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\App\RequestInterface;

class Index extends Action
{
    protected $resultRawFactory;
    protected $assetRepository;
    protected $directoryList;

    public function __construct(
        Context $context,
        RawFactory $resultRawFactory,
        Repository $assetRepository,
        \Magento\Framework\App\Filesystem\DirectoryList $directoryList
    ) {
        parent::__construct($context);
        $this->resultRawFactory = $resultRawFactory;
        $this->assetRepository = $assetRepository;
        $this->directoryList = $directoryList;
    }

    public function execute()
    {
        $request = $this->getRequest();
        $requestedPath = $request->getPathInfo();

        if ($requestedPath === '/gp-firebase' || $requestedPath === '/gp-firebase.js') {
            $resultRaw = $this->resultRawFactory->create();
            $moduleDir = $this->directoryList->getPath('app');
            $filePath = $moduleDir . '/code/Gopersonal/Magento/web/gp-firebase.js';
            $jsContent = file_get_contents($filePath);
            $resultRaw->setContents($jsContent);
            $resultRaw->setHeader('Content-Type', 'text/javascript');
            return $resultRaw;
        }

        // Handle other paths or return a 404 error
        $this->getResponse()->setHttpResponseCode(404);
        return $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW)->setContents('File not found');
    }
}