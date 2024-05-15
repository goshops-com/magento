<?php
namespace Gopersonal\Magento\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\App\Filesystem\DirectoryList;

class Index extends Action
{
    protected $resultRawFactory;
    protected $assetRepository;
    protected $directoryList;

    public function __construct(
        Context $context,
        RawFactory $resultRawFactory,
        Repository $assetRepository,
        DirectoryList $directoryList
    ) {
        parent::__construct($context);
        $this->resultRawFactory = $resultRawFactory;
        $this->assetRepository = $assetRepository;
        $this->directoryList = $directoryList;
    }

    public function execute()
    {
        $resultRaw = $this->resultRawFactory->create();
        $moduleDir = $this->directoryList->getPath('app');
        $filePath = $moduleDir . '/code/GoPersonal/Magento/web/gp-firebase.js';
        $jsContent = file_get_contents($filePath);
        $resultRaw->setContents($jsContent);
        $resultRaw->setHeader('Content-Type', 'application/javascript', true);
        return $resultRaw;
    }
}
