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
        $pathInfo = $this->$request->getPathInfo();

        // Read all query parameters from the request
        $queryParams = $this->request->getParams();
        $token = $this->cookieManager->getCookie('gopersonal_jwt');
        
        // Log the incoming URL
        $this->logger->info("Incoming URL: " . $this->request->getUriString());

        // Check if the 'q' parameter exists
        if ($pathInfo !== '/search' || !isset($queryParams['q'])) {
            // Log the ignored request
            $this->logger->info("Ignoring request. URL path is not /search or 'q' parameter is missing.");
            return $this;
        }

        // Log the query parameters
        $this->logger->info("Query Params: " . json_encode($queryParams));

        // Obtain the token from the cookie
        

        // Obtain the client ID from the configuration
        $clientId = $this->scopeConfig->getValue('gopersonal/general/client_id', ScopeInterface::SCOPE_STORE);

        // Determine the base URL based on the client ID
        $url = 'https://discover.gopersonal.ai/item/search?adapter=magento';
        if (strpos($clientId, 'D-') === 0) {
            $url = 'https://go-discover-dev.goshops.ai/item/search?adapter=magento';
        }

        // Extract the 'q' parameter and append it to the URL
        $searchTerm = $queryParams['q'];
        $queryParam = '&query=' . urlencode($searchTerm);

        // Append other query parameters as filters
        $filtersParam = '';
        if (!empty($queryParams)) {
            unset($queryParams['q']); // Remove 'q' parameter if already added separately
            $filtersParam = '&filters=' . urlencode(json_encode($queryParams));
        }

        $url .= $queryParam . $filtersParam;

        // Add authorization header and make the request
        $this->httpClient->addHeader("Authorization", "Bearer " . $token);
        $this->httpClient->get($url);
        $response = $this->httpClient->getBody();

        // Log the request URL
        $this->logger->info('Request', ['url' => $url]);

        // Log the response
        $this->logger->info('Response from API', ['response' => $response]);

        // Decode the response to get the product IDs
        $productIds = json_decode($response, true);

        // Check if product IDs are an array
        if (is_array($productIds)) {
            // Log the product IDs
            $this->logger->info("Product IDs: " . implode(',', $productIds));
        } else {
            // Log the error if product IDs are not an array
            $this->logger->error("Product IDs are not an array", ['product_ids' => $productIds]);
        }

        // Set the product IDs into the request
        $this->request->setParam('product_ids', $productIds);

        return $this;
    }
}