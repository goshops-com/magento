<?php
namespace GoPersonal\Magento\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class CustomSearchResultPlugin
{
    protected $scopeConfig;
    protected $resultJsonFactory;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->resultJsonFactory = $resultJsonFactory;
    }

    public function aroundBuild(
        \Magento\CatalogSearch\Model\Search\IndexBuilder $subject,
        \Closure $proceed
    ) {
        $isEnabled = $this->scopeConfig->getValue(
            'gopersonal/general/gopersonal_has_search',
            ScopeInterface::SCOPE_STORE
        );

        if ($isEnabled == '1') {
            $result = $this->resultJsonFactory->create();
            // This is a placeholder JSON object, you can structure it according to the API's expected format
            $productData = [
                'product_id' => 45,
                'name' => 'Sample Product',
                'price' => 99.99,
                'description' => 'This is a sample product from hardcoded search result.'
            ];
            return $result->setData($productData);
        }

        // If GoPersonal search is not enabled, proceed with Magento's default behavior
        return $proceed();
    }
}
