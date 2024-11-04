<?php
namespace Gopersonal\Magento\Plugin;

use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Registry;
// ... (keep existing use statements)

class SearchEngine extends MagentoSearchEngine
{
    protected $registry;
    
    public function __construct(
        AdapterFactory $adapterFactory,
        IntervalFactory $intervalFactory,
        LoggerInterface $logger,
        HttpRequestInterface $httpRequest,
        ObjectManagerInterface $objectManager,
        Registry $registry
    ) {
        $this->registry = $registry;
        // ... (keep existing constructor assignments)
        parent::__construct($adapterFactory, $intervalFactory);
    }

    public function search(RequestInterface $request)
    {
        if (!$this->httpRequest->getParam('gpSearchOverride')) {
            var_dump("USING DEFAULT MAGENTO SEARCH");
            return parent::search($request);
        }

        var_dump("USING CUSTOM SEARCH ENGINE");
        
        try {
            // Your existing products array
            $products = [
                [
                    'entity_id' => '1',
                    'name' => 'Test Product 1',
                    'price' => 99.99,
                    'sku' => 'TEST-1'
                ],
                [
                    'entity_id' => '2',
                    'name' => 'Test Product 2',
                    'price' => 149.99,
                    'sku' => 'TEST-2'
                ]
            ];

            // Store product IDs in registry for layered navigation
            $productIds = array_column($products, 'entity_id');
            $this->registry->register('custom_search_product_ids', $productIds, true);
            
            var_dump("Stored product IDs in registry:", $productIds);

            // ... (rest of your existing search implementation)
        } catch (\Exception $e) {
            var_dump("Error in search engine:", $e->getMessage());
            throw $e;
        }
    }
}