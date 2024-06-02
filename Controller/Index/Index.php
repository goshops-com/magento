<?php
namespace Gopersonal\Magento\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\View\Result\PageFactory;
use Psr\Log\LoggerInterface;
use Magento\Framework\Session\SessionManagerInterface;

class Index extends Action
{
    protected $resultRawFactory;
    protected $directoryList;
    protected $pageFactory;
    protected $logger;
    protected $session;

    public function __construct(
        Context $context,
        RawFactory $resultRawFactory,
        DirectoryList $directoryList,
        PageFactory $pageFactory,
        LoggerInterface $logger,
        SessionManagerInterface $session
    ) {
        parent::__construct($context);
        $this->resultRawFactory = $resultRawFactory;
        $this->directoryList = $directoryList;
        $this->pageFactory = $pageFactory;
        $this->logger = $logger;
        $this->session = $session;
    }

    public function execute()
    {
        // Log entry into the execute method
        $this->logger->info('Entered2 execute method');

        // Check if the method is being executed more than once per request
        if ($this->session->getData('executed_once')) {
            $this->logger->info('Execute method already run, skipping.');
            return;
        } else {
            $this->session->setData('executed_once', true);
        }

        // Get the front name from the request
        $frontName = $this->getRequest()->getFrontName();
        $this->logger->info('Front name: ' . $frontName);

        // Custom logic for specific front names
        if ($frontName === 'gp-firebase') {
            $resultRaw = $this->resultRawFactory->create();

            $moduleDir = $this->directoryList->getPath('app');
            $filePath = $moduleDir . '/code/Gopersonal/Magento/web/gp-firebase.js';
            $this->logger->info('File path: ' . $filePath);

            $jsContent = file_get_contents($filePath);
            $this->logger->info('JS Content: ' . substr($jsContent, 0, 100) . '...'); // Log the beginning of the content

            $resultRaw->setContents($jsContent);
            $resultRaw->setHeader('Content-Type', 'text/javascript');
            $this->logger->info('Returning JS content');
            return $resultRaw;
        }

        // Default behavior for other front names or general case
        $this->logger->info('Returning default page factory');
        return $this->pageFactory->create();
    }
}
