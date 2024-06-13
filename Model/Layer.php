<?php
/**
 * @package   Gopersonal_Magento
 * author    Shahid Taj
 */
namespace Gopersonal\Magento\Model;

use Magento\Catalog\Model\Layer\ContextInterface;
use Magento\Catalog\Model\Layer\StateFactory;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Registry;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as ProductAttributeCollectionFactory;

class Layer extends \Magento\Catalog\Model\Layer
{
    protected $logger;
    protected $cache;
    protected $eavConfig;
    protected $productAttributeCollectionFactory;
    protected $cacheKey = 'gopersonal_layer_filter_counts';
    protected $request;

    public function __construct(
        ContextInterface $context,
        StateFactory $stateFactory,
        AttributeCollectionFactory $attributeCollectionFactory,
        Product $catalogProduct,
        StoreManagerInterface $storeManager,
        Registry $registry,
        CategoryRepositoryInterface $categoryRepository,
        LoggerInterface $logger,
        CacheInterface $cache,
        RequestInterface $request,
        EavConfig $eavConfig,
        ProductAttributeCollectionFactory $productAttributeCollectionFactory,
        array $data = []
    ) {
        $this->logger = $logger;
        $this->cache = $cache;
        $this->eavConfig = $eavConfig;
        $this->productAttributeCollectionFactory = $productAttributeCollectionFactory;
        $this->request = $request;
        parent::__construct(
            $context, 
            $stateFactory, 
            $attributeCollectionFactory, 
            $catalogProduct, 
            $storeManager, 
            $registry, 
            $categoryRepository, 
            $data
        );
    }

    public function getProductCollection()
    {
        $this->logger->info('Starting getProductCollection method');
        
        $collection = parent::getProductCollection();
        $idArray = $this->getProductsIds();

        if (!empty($idArray)) {
            $this->logger->info('Filtering collection by product IDs: ' . implode(',', $idArray));
            $collection->addAttributeToFilter('entity_id', ['in' => $idArray]);

            // Custom sorting based on array order 
            $collection->getSelect()->order(
                new \Zend_Db_Expr('FIELD(e.entity_id, ' . implode(',', $idArray) . ')')
            );
        }

        // Add filterable attributes to the collection
        $filterableAttributes = $this->getFilterableAttributes();
        $this->logger->info('Filterable Attributes: ' . json_encode($filterableAttributes));
        foreach ($filterableAttributes as $attribute) {
            $collection->addAttributeToSelect($attribute->getAttributeCode());
        }

        $this->logger->info('Finished getProductCollection method');

        // Check if filter counts are already in the request
        if ($this->request->getParam('filter_counts') !== null) {
            $filterCounts = $this->request->getParam('filter_counts');
            $this->logger->info('Returning cached filter counts from request');
        } else {
            // Calculate filter counts and store in the request
            $filterCounts = $this->calculateFilterCounts($collection);
            $this->request->setParam('filter_counts', $filterCounts);
            $this->logger->info('Calculated and stored filter counts in request');
        }

        $this->setData('filter_counts', $filterCounts);

        return $collection;
    }

    public function getProductsIds()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $helper = $objectManager->get(\Gopersonal\Magento\Helper\Data::class);

        return $helper->getProductsIds('layer');
    }

    private function getFilterableAttributes()
    {
        $attributeCollection = $this->productAttributeCollectionFactory->create();
        $attributeCollection->addIsFilterableFilter();

        $filterableAttributes = [];
        foreach ($attributeCollection as $attribute) {
            $filterableAttributes[] = $this->eavConfig->getAttribute('catalog_product', $attribute->getAttributeCode());
        }

        return $filterableAttributes;
    }

    private function calculateFilterCounts(\Magento\Catalog\Model\ResourceModel\Product\Collection $collection)
    {
        $filterCounts = [];
        $this->logger->info('Starting filter count calculation');

        // Fetch filterable attributes dynamically
        $filterableAttributes = $this->getFilterableAttributes();
        $this->logger->info('Filterable Attributes: ' . json_encode($filterableAttributes));

        // Iterate over filterable attributes 
        foreach ($filterableAttributes as $attribute) {
            $attributeCode = $attribute->getAttributeCode();

            // Skip the price attribute
            if ($attributeCode == 'price') {
                continue;
            }

            $filterCounts[$attributeCode] = [];

            // Get the attribute model (if it exists)
            $attribute = $collection->getResource()->getAttribute($attributeCode);

            // Check if the attribute exists and has options
            if ($attribute && $attribute->usesSource()) {
                // Get all possible options for the attribute
                $attributeOptions = $attribute->getSource()->getAllOptions(false);

                // Create a map of option values to labels
                $optionMap = [];
                foreach ($attributeOptions as $option) {
                    $optionMap[$option['value']] = $option['label'];
                }
                $this->logger->info('Option map for attribute ' . $attributeCode . ': ' . json_encode($optionMap));

                // Iterate through each product to collect filter data
                foreach ($collection as $product) {
                    $productAttributeValue = $product->getData($attributeCode);
                    $this->logger->debug('Product ID ' . $product->getId() . ' has attribute ' . $attributeCode . ' with value "' . $productAttributeValue . '"');
                    $this->logger->debug('Product data: ' . json_encode($product->getData(), JSON_PRETTY_PRINT));

                    // Ensure the product attribute value is in the map
                    if (is_string($productAttributeValue) && strpos($productAttributeValue, ',') !== false) {
                        $values = explode(',', $productAttributeValue);
                    } else {
                        $values = [$productAttributeValue];
                    }

                    foreach ($values as $value) {
                        if (isset($optionMap[$value])) {
                            if (!isset($filterCounts[$attributeCode][$value])) {
                                $filterCounts[$attributeCode][$value] = 0;
                            }
                            $filterCounts[$attributeCode][$value]++;
                            $this->logger->debug('Filter item "' . $optionMap[$value] . '" (' . $value . ') has count: ' . $filterCounts[$attributeCode][$value]);
                        } else {
                            $this->logger->warning('Attribute value "' . $value . '" for attribute ' . $attributeCode . ' not found in option map');
                        }
                    }
                }

                // Remove filter options with zero count
                foreach ($filterCounts[$attributeCode] as $optionKey => $optionValue) {
                    if ($optionValue == 0) {
                        unset($filterCounts[$attributeCode][$optionKey]);
                    }
                }
            } else {
                $this->logger->warning("Attribute '$attributeCode' not found or doesn't use a source model");
            }
        }

        $this->logger->info('Finished filter count calculation');
        $this->logger->info('Final filter counts: ' . json_encode($filterCounts));

        return $filterCounts;
    }
}
