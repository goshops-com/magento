<?php

namespace Gopersonal\Magento\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

class Data extends AbstractHelper
{
    protected $scopeConfig;
    protected $httpClient;
    protected $logger;

    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        Curl $httpClient,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->scopeConfig = $scopeConfig;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    public function getProductIds($query, $token)
    {
        try {
            $clientId = $this->scopeConfig->getValue('gopersonal/general/client_id', ScopeInterface::SCOPE_STORE);
            $url = 'https://discover.gopersonal.ai/item/search?adapter=magento';

            if (strpos($clientId, 'D-') === 0) {
                $url = 'https://go-discover-dev.goshops.ai/item/search?adapter=magento';
            }

            $url .= '&query=' . urlencode($query);

            $this->httpClient->addHeader("Authorization", "Bearer " . $token);
            $this->httpClient->get($url);
            $response = $this->httpClient->getBody();

            $productIds = json_decode($response, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($productIds)) {
                return $productIds;
            } else {
                $this->logger->error('Invalid JSON response: ' . json_last_error_msg());
                return [];
            }
        } catch (\Exception $e) {
            $this->logger->error('Error fetching product IDs: ' . $e->getMessage());
            return [];
        }
    }
}
