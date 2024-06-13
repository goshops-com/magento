<?php
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
use Magento\Framework\App\RequestInterface;

class Layer extends \Magento\Catalog\Model\Layer
{
    protected $logger;
    protected $filterableAttributeList;
    protected $cache;
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
        FilterableAttributeList $filterableAttributeList,
        LoggerInterface $logger,
        CacheInterface $cache,
        RequestInterface $request,
        array $data = []
    ) {
        $this->logger = $logger;
        $this->filterableAttributeList = $filterableAttributeList;
        $this->cache = $cache;
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

        // Ensure the collection loads the filterable attributes
        $filterableAttributes = $this->filterableAttributeList->getList();
        foreach ($filterableAttributes as $attribute) {
            $collection->addAttributeToSelect($attribute->getAttributeCode());
        }
        $this->logger->info('Filterable Attributes: ' . json_encode($filterableAttributes));

        $this->logger->info('Finished getProductCollection method');

        // Check if filters are already in the request
        if ($this->request->getParam('filter_counts') !== null && $this->request->getParam('filter_data') !== null) {
            $this->logger->info('Filters already set in the request');
            return $collection;
        }

        // Calculate filter counts
        $filterCounts = $this->calculateFilterCounts($collection);
        $this->logger->info('Calculated filter counts: ' . json_encode($filterCounts));
        $this->request->setParam('filter_counts', $filterCounts);
        $this->logger->info('Stored filter counts in the request');

        // Set filter data
        $filterData = $this->getFilterData($collection);
        $this->logger->info('Calculated filter data: ' . json_encode($filterData));
        $this->request->setParam('filter_data', $filterData);
        $this->logger->info('Stored filter data in the request');

        return $collection;
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

        $filterableAttributes = $this->filterableAttributeList->getList();

        foreach ($filterableAttributes as $attribute) {
            $attributeCode = $attribute->getAttributeCode();
            $this->logger->info('Processing attribute: ' . $attributeCode);

            if ($attributeCode == 'price') {
                continue;
            }

            $filterCounts[$attributeCode] = [];

            $attribute = $collection->getResource()->getAttribute($attributeCode);

            if ($attribute && $attribute->usesSource()) {
                $attributeOptions = $attribute->getSource()->getAllOptions(false);

                $optionMap = [];
                foreach ($attributeOptions as $option) {
                    $optionMap[$option['value']] = $option['label'];
                }

                foreach ($collection as $product) {
                    $productAttributeValue = $product->getData($attributeCode);
                    $this->logger->debug('Product ID ' . $product->getId() . ' has attribute ' . $attributeCode . ' with value "' . $productAttributeValue . '"');
                    $this->logger->debug('Product data: ' . json_encode($product->getData(), JSON_PRETTY_PRINT));

                    if ($productAttributeValue) {
                        $productAttributeValues = explode(',', $productAttributeValue);
                        foreach ($productAttributeValues as $value) {
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
                }

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

    private function getFilterData(\Magento\Catalog\Model\ResourceModel\Product\Collection $collection)
    {
        $filterData = [];

        $filterableAttributes = $this->filterableAttributeList->getList();

        foreach ($filterableAttributes as $attribute) {
            $attributeCode = $attribute->getAttributeCode();

            if ($attributeCode == 'price') {
                continue;
            }

            $attribute = $collection->getResource()->getAttribute($attributeCode);

            if ($attribute && $attribute->usesSource()) {
                $attributeOptions = $attribute->getSource()->getAllOptions(false);

                foreach ($attributeOptions as $option) {
                    $filterData[$attributeCode][$option['value']] = $option['label'];
                }
            }
        }

        return $filterData;
    }
}
