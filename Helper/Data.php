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
        // Log the request ID
        $this->logger->info('Processing request', ['request_id' => $this->requestId]);

        // Check if the product IDs are already stored in the request
        if ($this->request->getParam('product_ids') !== null) {
            $productIds = $this->request->getParam('product_ids');
            return $productIds;
        }

        try {
            $q = $this->request->getParam('q', '');

            // Obtain the token from the cookie
            $token = $this->cookieManager->getCookie('gopersonal_jwt');

            // Obtain the client ID from the configuration
            $clientId = $this->scopeConfig->getValue('gopersonal/general/client_id', ScopeInterface::SCOPE_STORE);

            // Determine the base URL based on the client ID
            $url = 'https://discover.gopersonal.ai/item/search?adapter=magento';
            if (strpos($clientId, 'D-') === 0) {
                $url = 'https://go-discover-dev.goshops.ai/item/search?adapter=magento';
            }

            // Build filters parameter
            $filtersJson = [];
            foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
                foreach ($filterGroup->getFilters() as $filter) {
                    $field = $filter->getField();
                    $value = $filter->getValue();
                    if (!isset($filtersJson[$field])) {
                        $filtersJson[$field] = [];
                    }
                    $filtersJson[$field][] = $value;
                }
            }
            $filtersParam = !empty($filtersJson) ? '&filters=' . urlencode(json_encode($filtersJson)) : '';
            $url .= $filtersParam;

            // Add authorization header and make the request
            $this->httpClient->addHeader("Authorization", "Bearer " . $token);
            $this->httpClient->get($url);
            $response = $this->httpClient->getBody();

            // Log the response
            $this->logger->info('Response from API', ['response' => $response]);

            // Decode the response to get the product IDs
            $productIds = json_decode($response);

            // Store the generated product IDs in the request
            $this->request->setParam('product_ids', $productIds);
        } finally {
            // Remove the temporary flag
            $this->request->setParam('product_ids_generation_in_progress', null);
        }

        return $productIds;
    }
}
