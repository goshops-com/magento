<?php
namespace GoPersonal\Magento\Controller\Index; // Adjusted namespace

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\View\Asset\Repository;

class Index extends Action
{
    protected $resultRawFactory;
    protected $assetRepository;

    public function __construct(
        Context $context,
        RawFactory $resultRawFactory,
        Repository $assetRepository
    ) {
        parent::__construct($context);
        $this->resultRawFactory = $resultRawFactory;
        $this->assetRepository = $assetRepository;
    }

    public function execute()
    {
        $resultRaw = $this->resultRawFactory->create();

        $filePath = $this->assetRepository->createAsset('GoPersonal_Magento::js/gp-firebase.js')->getPath(); // Adjusted asset path
        $jsContent = file_get_contents($filePath);

        $resultRaw->setContents($jsContent);
        $resultRaw->setHeader('Content-Type', 'text/javascript'); 
        return $resultRaw;
    }
}