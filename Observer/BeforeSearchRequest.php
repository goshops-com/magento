<?php
namespace Gopersonal\Magento\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Store\Model\ScopeInterface;

class BeforeSearchRequest implements ObserverInterface
{
    protected $request;
    protected $logger;
    protected $scopeConfig;
    protected $httpClient;
    protected $cookieManager;

    public function __construct(
        RequestInterface $request,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig,
        ClientInterface $httpClient,
        CookieManagerInterface $cookieManager
    ) {
        $this->request = $request;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->httpClient = $httpClient;
        $this->cookieManager = $cookieManager;
    }

    public function execute(Observer $observer)
    {
        // Read all query parameters from the request
        $queryParams = $this->request->getParams();

        // Log the query parameters
        $this->logger->info("Query Params: " . json_encode($queryParams));

        // Obtain the token from the cookie
        $token = $this->cookieManager->getCookie('gopersonal_jwt');

        // Obtain the client ID from the configuration
        $clientId = $this->scopeConfig->getValue('gopersonal/general/client_id', ScopeInterface::SCOPE_STORE);

        // Determine the base URL based on the client ID
        $url = 'https://discover.gopersonal.ai/item/search?adapter=magento';
        if (strpos($clientId, 'D-') === 0) {
            $url = 'https://go-discover-dev.goshops.ai/item/search?adapter=magento';
        }

        // Append query parameters to the URL
        $queryParamString = http_build_query($queryParams);
        $url .= '&' . $queryParamString;

        // Add authorization header and make the request
        $this->httpClient->addHeader("Authorization", "Bearer " . $token);
        $this->httpClient->get($url);
        $response = $this->httpClient->getBody();

        $this->logger->info('Request', ['url' => $url]);

        // Log the response
        $this->logger->info('Response from API', ['response' => $response]);

        // Decode the response to get the product IDs
        $productIds = json_decode($response, true);

        // Log the product IDs
        $this->logger->info("Product IDs: " . implode(',', $productIds));

        // Set the product IDs into the request
        $this->request->setParam('product_ids', $productIds);

        return $this;
    }
}
