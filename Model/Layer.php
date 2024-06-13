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
use Magento\Catalog\Model\Layer\Category\FilterableAttributeList;
use Magento\Framework\App\CacheInterface;

class Layer extends \Magento\Catalog\Model\Layer
{
    protected $logger;
    protected $filterableAttributeList;
    protected $cache;
    protected $cacheKey = 'gopersonal_layer_filter_counts';

    public function __construct(
        ContextInterface $context,
        StateFactory $stateFactory,
        AttributeCollectionFactory $attributeCollectionFactory,
        Product $catalogProduct,
        StoreManagerInterface $storeManager,
        Registry $registry,
        CategoryRepositoryInterface $categoryRepository,
        FilterableAttributeList $filterableAttributeList,
        LoggerInterface $logger,
        CacheInterface $cache,
        array $data = []
    ) {
        $this->logger = $logger;
        $this->filterableAttributeList = $filterableAttributeList;
        $this->cache = $cache;
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

            // Dynamically load filterable attributes
            $filterableAttributes = $this->filterableAttributeList->getList();
            $attributesToLoad = [];
            foreach ($filterableAttributes as $attribute) {
                $attributesToLoad[] = $attribute->getAttributeCode();
            }
            $collection->addAttributeToSelect($attributesToLoad);

            // Custom sorting based on array order 
            $collection->getSelect()->order(
                new \Zend_Db_Expr('FIELD(e.entity_id, ' . implode(',', $idArray) . ')')
            );
        }

        $this->logger->info('Finished getProductCollection method');

        // Check cache for filter counts
        $filterCounts = $this->calculateFilterCounts($collection);
        $this->cache->save(serialize($filterCounts), $this->cacheKey, [], 3600);
        $this->logger->info('Calculated and cached filter counts');

        $this->setData('filter_counts', $filterCounts);

        return $collection;
    }

    public function getFilterCounts()
    {
        return $this->getData('filter_counts');
    }

    public function getProductsIds()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $helper = $objectManager->get(\Gopersonal\Magento\Helper\Data::class);

        return $helper->getProductsIds('layer');
    }

    private function calculateFilterCounts(\Magento\Catalog\Model\ResourceModel\Product\Collection $collection)
    {
        $filterCounts = [];
        $this->logger->info('Starting filter count calculation');

        // Fetch filterable attributes dynamically
        $filterableAttributes = $this->filterableAttributeList->getList();

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

                // Iterate through each product to collect filter data
                foreach ($collection as $product) {
                    $productAttributeValue = $product->getData($attributeCode);
                    $this->logger->debug('Product ID ' . $product->getId() . ' has attribute ' . $attributeCode . ' with value "' . $productAttributeValue . '"');
                    $this->logger->debug('Product data: ' . json_encode($product->getData(), JSON_PRETTY_PRINT));

                    // Ensure the product attribute value is in the map
                    if (isset($optionMap[$productAttributeValue])) {
                        if (!isset($filterCounts[$attributeCode][$productAttributeValue])) {
                            $filterCounts[$attributeCode][$productAttributeValue] = 0;
                        }
                        $filterCounts[$attributeCode][$productAttributeValue]++;
                        $this->logger->debug('Filter item "' . $optionMap[$productAttributeValue] . '" (' . $productAttributeValue . ') has count: ' . $filterCounts[$attributeCode][$productAttributeValue]);
                    } else {
                        $this->logger->warning('Attribute value "' . $productAttributeValue . '" for attribute ' . $attributeCode . ' not found in option map');
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

        return $filterCounts;
    }
}
