<?php
namespace Gopersonal\Magento\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\UrlRewrite\Model\UrlRewriteFactory;
use Magento\Store\Model\StoreManagerInterface;

class InstallData implements InstallDataInterface
{
    protected $urlRewriteFactory;
    protected $storeManager;

    public function __construct(
        UrlRewriteFactory $urlRewriteFactory,
        StoreManagerInterface $storeManager
    ) {
        $this->urlRewriteFactory = $urlRewriteFactory;
        $this->storeManager = $storeManager;
    }

    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        $storeId = $this->storeManager->getStore()->getId();

        $urlRewrite = $this->urlRewriteFactory->create();
        $urlRewrite->setStoreId($storeId);
        $urlRewrite->setIsSystem(0);
        $urlRewrite->setIdPath('gp-firebase-js');
        $urlRewrite->setRequestPath('gp-firebase.js');
        $urlRewrite->setTargetPath('gp-firebase-js/index/index');

        $urlRewrite->save();

        $setup->endSetup();
    }
}
