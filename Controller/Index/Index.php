<?php
namespace Gopersonal\Magento\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\View\Result\PageFactory;
use Psr\Log\LoggerInterface; // Add the LoggerInterface

class Index extends Action
{
    protected $resultRawFactory;
    protected $directoryList;
    protected $pageFactory;
    protected $logger; // Add the logger property

    public function __construct(
        Context $context,
        RawFactory $resultRawFactory,
        DirectoryList $directoryList,
        PageFactory $pageFactory,
        LoggerInterface $logger // Inject the logger
    ) {
        parent::__construct($context);
        $this->resultRawFactory = $resultRawFactory;
        $this->directoryList = $directoryList;
        $this->pageFactory = $pageFactory;
        $this->logger = $logger; // Assign the logger
    }

    public function execute()
    {
        // Get the front name from the request
        $frontName = $this->getRequest()->getFrontName();

        // Custom logic for specific front names
        if ($frontName === 'gp-firebase') {
            $resultRaw = $this->resultRawFactory->create();

            $moduleDir = $this->directoryList->getPath('app');
            $filePath = $moduleDir . '/code/Gopersonal/Magento/web/gp-firebase.js';
            
            $jsContent = file_get_contents($filePath);
            
            $resultRaw->setContents($jsContent);
            $resultRaw->setHeader('Content-Type', 'text/javascript');
            return $resultRaw;
        }

        return $this->pageFactory->create();
    }
}
