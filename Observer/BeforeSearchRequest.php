<?php
namespace Gopersonal\Magento\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\ObjectManager;
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
        // Read the 'q' and '_gsSearchId' parameters from the URL
        $queryParam = $this->request->getParam('q');
        $gsSearchIdParam = $this->request->getParam('_gsSearchId');

        // Log the parameters
        $this->logger->info("Query Param: $queryParam, GS Search ID Param: $gsSearchIdParam");

        // Obtain the token from the cookie
        $token = $this->cookieManager->getCookie('gopersonal_jwt');

        // Obtain the client ID from the configuration
        $clientId = $this->scopeConfig->getValue('gopersonal/general/client_id', ScopeInterface::SCOPE_STORE);

        // Determine the base URL based on the client ID
        $url = 'https://discover.gopersonal.ai/item/search?adapter=magento';
        if (strpos($clientId, 'D-') === 0) {
            $url = 'https://go-discover-dev.goshops.ai/item/search?adapter=magento';
        }

        // Build filters parameter (this example assumes $searchCriteria is obtained or constructed somehow)
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
        $productIds = json_decode($response, true);

        // Log the product IDs
        $this->logger->info("Product IDs: " . implode(',', $productIds));

        return $this;
    }
}
