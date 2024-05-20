use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Exception\LocalizedException;

class CustomSearch implements SearchInterface
{
    protected $httpClient;
    protected $scopeConfig;
    protected $resultJsonFactory;
    protected $logger;
    protected $defaultSearchEngine;
    protected $searchResultFactory;
    protected $cookieManager;
    protected $searchCriteriaBuilder;
    protected $productCollectionFactory;

    public function __construct(
        ClientInterface $httpClient,
        ScopeConfigInterface $scopeConfig,
        JsonFactory $resultJsonFactory,
        LoggerInterface $logger,
        SearchEngine $defaultSearchEngine,
        SearchResultFactory $searchResultFactory,
        CookieManagerInterface $cookieManager,
        CustomerSession $customerSession,
        SearchRequestBuilder $searchRequestBuilder,
        ProductCollectionFactory $productCollectionFactory
    ) {
        $this->httpClient = $httpClient;
        $this->scopeConfig = $scopeConfig;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->logger = $logger;
        $this->defaultSearchEngine = $defaultSearchEngine;
        $this->searchResultFactory = $searchResultFactory;
        $this->cookieManager = $cookieManager;
        $this->searchRequestBuilder = $searchRequestBuilder;
        $this->productCollectionFactory = $productCollectionFactory;
    }

    private function getQueryFromSearchCriteria(SearchCriteriaInterface $searchCriteria)
    {
        foreach ($searchCriteria->getFilterGroups() as $group) {
            foreach ($group->getFilters() as $filter) {
                if ($filter->getField() === 'search_term') {
                    return $filter->getValue();
                }
            }
        }
        return null;
    }

    public function search(SearchCriteriaInterface $searchCriteria)
    {
        try {
            $isEnabled = $this->scopeConfig->getValue(
                'gopersonal/general/gopersonal_has_search',
                ScopeInterface::SCOPE_STORE
            );

            $searchTerm = $this->getQueryFromSearchCriteria($searchCriteria);

            if ($isEnabled != 'YES' || empty($searchTerm)) {
                $this->logger->info('CustomSearch: Fallback to default search engine (no search term or disabled)');
                return $this->defaultSearchEngine->search($searchCriteria);
            }

            if ($isEnabled == 'YES') {
                $this->logger->info('CustomSearch: External search is enabled');

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

                $token = $this->cookieManager->getCookie('gopersonal_jwt');

                if (!$token) {
                    $this->logger->info('No API token found in session.');
                    $searchResult = $this->searchResultFactory->create();
                    $searchResult->setSearchCriteria($searchCriteria);

                    $itemData = ['id' => 1556];
                    $item = new \Magento\Framework\DataObject($itemData);

                    $searchResult->setItems([$item]);
                    $searchResult->setTotalCount(1);

                    return $searchResult;
                }

                $clientId = $this->scopeConfig->getValue('gopersonal/general/client_id', ScopeInterface::SCOPE_STORE);
                $url = 'https://discover.gopersonal.ai/item/search?adapter=magento';

                if (strpos($clientId, 'D-') === 0) {
                    $url = 'https://go-discover-dev.goshops.ai/item/search?adapter=magento';
                }

                $query = $searchTerm;
                $queryParam = $query ? '&query=' . urlencode($query) : '';
                $filtersParam = !empty($filtersJson) ? '&filters=' . urlencode(json_encode($filtersJson)) : '';

                $url .= $queryParam . $filtersParam;

                $this->httpClient->addHeader("Authorization", "Bearer " . $token);

                $this->httpClient->get($url);
                $response = $this->httpClient->getBody();
                $productIds = json_decode($response);

                $searchResult = $this->searchResultFactory->create();
                $searchResult->setSearchCriteria($searchCriteria);

                // Fetch products in bulk using product collection
                $productCollection = $this->productCollectionFactory->create()
                    ->addAttributeToSelect('*')
                    ->addIdFilter($productIds);

                // Apply the original search criteria filters to the product collection
                foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
                    foreach ($filterGroup->getFilters() as $filter) {
                        $productCollection->addAttributeToFilter($filter->getField(), $filter->getValue());
                    }
                }

                $items = [];
                foreach ($productCollection as $product) {
                    $itemData = ['id' => $product->getId()];
                    $items[] = new \Magento\Framework\DataObject($itemData);
                }

                $searchResult->setItems($items);
                $searchResult->setTotalCount($productCollection->getSize());

                return $searchResult;
            }
        } catch (\Exception $e) {
            $this->logger->error('CustomSearch: Error occurred during search', ['exception' => $e]);
            return $this->defaultSearchEngine->search($searchCriteria);
        }
    }
}
