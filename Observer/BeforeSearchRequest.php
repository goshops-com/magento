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
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Exception;

class BeforeSearchRequest implements ObserverInterface
{
    protected $request;
    protected $logger;
    protected $scopeConfig;
    protected $httpClient;
    protected $cookieManager;
    protected $sessionManager;
    protected $productCollectionFactory;

    public function __construct(
        RequestInterface $request,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig,
        ClientInterface $httpClient,
        CookieManagerInterface $cookieManager,
        SessionManagerInterface $sessionManager,
        ProductCollectionFactory $productCollectionFactory
    ) {
        $this->request = $request;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->httpClient = $httpClient;
        $this->cookieManager = $cookieManager;
        $this->sessionManager = $sessionManager;
        $this->productCollectionFactory = $productCollectionFactory;
    }

    public function execute(Observer $observer)
    {
        $pathInfo = $this->request->getPathInfo();

        // Read all query parameters from the request
        $queryParams = $this->request->getParams();
        $token = $this->cookieManager->getCookie('gopersonal_jwt');

        // Log the incoming URL
        $this->logger->info("Incoming URL: " . $this->request->getUriString());

        // Check if the 'q' parameter exists
        if (strpos($pathInfo, '/gp-search') !== 0 || !isset($queryParams['q'])) {
            // Log the ignored request
            $this->logger->info("Ignoring request. URL path does not start with /search or 'q' parameter is missing.");
            return $this;
        }

        // Log the query parameters
        $this->logger->info("Query Params: " . json_encode($queryParams));

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

        // Include the _gsSearchId if it exists
        if (isset($queryParams['_gsSearchId'])) {
            $gsSearchIdParam = '&_gsSearchId=' . urlencode($queryParams['_gsSearchId']);
            $url .= $gsSearchIdParam;
        }

        $url .= $queryParam . $filtersParam;

        $attempts = 0;
        $maxAttempts = 2;
        $success = false;

        while ($attempts < $maxAttempts && !$success) {
            try {
                // Add authorization header and make the request
                $this->httpClient->addHeader("Authorization", "Bearer " . $token);
                $this->httpClient->get($url);
                $response = $this->httpClient->getBody();
                $statusCode = $this->httpClient->getStatus();

                // Log the request URL
                $this->logger->info('Request', ['url' => $url]);

                if ($statusCode == 401) {
                    // Get the session ID
                    $sessionId = $this->sessionManager->getSessionId();
                    $sessionParam = '&externalSessionId=' . urlencode($sessionId);

                    // Append the client ID parameter
                    $clientIdParam = '&clientId=' . urlencode($clientId);

                    // Add session fallback parameter
                    $sessionFallbackParam = '&sessionFallback=true';

                    // Retry with additional parameters
                    $url .= $sessionParam . $clientIdParam . $sessionFallbackParam;

                    // Retry the request
                    $this->httpClient->get($url);
                    $response = $this->httpClient->getBody();
                    $statusCode = $this->httpClient->getStatus();

                    // Log the retry request URL
                    $this->logger->info('Retry Request', ['url' => $url]);
                }

                // Log the response
                $this->logger->info('Response from API', ['response' => $response]);

                // Decode the response to get the product IDs
                $productIds = json_decode($response, true);

                // Check if product IDs are an array
                if (is_array($productIds)) {
                    // Log the product IDs
                    if (empty($productIds)) {
                        $productIds = [-1];
                    }

                    $this->logger->info("Product IDs: " . implode(',', $productIds));

                    // Set the product IDs into the request
                    $this->request->setParam('product_ids', $productIds);
                } else {
                    // Log the error if product IDs are not an array
                    $this->logger->error("Product IDs are not an array", ['product_ids' => $productIds]);

                    $this->logger->info('performNativeSearch');
                    // Perform native Magento search as fallback
                    $this->performNativeSearch($searchTerm);
                }

                $success = true;
            } catch (Exception $e) {
                $attempts++;
                $this->logger->error("Request attempt $attempts failed: " . $e->getMessage());

                if ($attempts >= $maxAttempts) {
                    // Perform native Magento search as fallback
                    $this->logger->info('performNativeSearch');
                    $this->performNativeSearch($searchTerm);
                    break;
                }
            }
        }

        return $this;
    }

    protected function performNativeSearch($searchTerm)
    {
        try {
            // Perform Magento native search
            $productCollection = $this->productCollectionFactory->create();
            $productCollection->addAttributeToSelect('*');
            $productCollection->addAttributeToFilter(
                [
                    ['attribute' => 'name', 'like' => '%' . $searchTerm . '%'],
                    ['attribute' => 'description', 'like' => '%' . $searchTerm . '%']
                ]
            );
            // Add sorting by relevance or other criteria
            $productCollection->setOrder('relevance', 'DESC');

            $productIds = $productCollection->getAllIds();

            // Log the native search results
            $this->logger->info("Native Search Product IDs: " . implode(',', $productIds));

            // Set the product IDs into the request
            $this->request->setParam('product_ids', $productIds);
        } catch (Exception $e) {
            $this->logger->error("Native search failed: " . $e->getMessage());
        }
    }
}
