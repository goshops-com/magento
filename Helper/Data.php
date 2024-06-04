<?php

namespace Gopersonal\Magento\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Request\Http;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    protected $request;
    protected $logger;
    protected $requestId;
    protected $scopeConfig;
    protected $httpClient;
    protected $cookieManager;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        Http $request,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig,
        ClientInterface $httpClient,
        CookieManagerInterface $cookieManager
    ) {
        $this->request = $request;
        $this->logger = $logger;
        $this->requestId = uniqid();  // Generate a unique request ID
        $this->scopeConfig = $scopeConfig;
        $this->httpClient = $httpClient;
        $this->cookieManager = $cookieManager;
        parent::__construct($context);
    }

    public function getProductsIds($flag = null)
    {
        // Check if the product IDs are already stored in the request
        if ($this->request->getParam('product_ids') !== null) {
            $productIds = $this->request->getParam('product_ids');
            return $productIds;
        }
    }
}
