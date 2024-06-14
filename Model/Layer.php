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

        // Apply URL filters to the collection
        $this->applyUrlFilters($collection);

        // Calculate and set filter data if not already in the request
        if ($this->request->getParam('filter_data_combined') === null) {
            $filterDataCombined = $this->getCombinedFilterData($collection);
            $this->logger->info('Calculated combined filter data: ' . json_encode($filterDataCombined));
            $this->request->setParam('filter_data_combined', $filterDataCombined);
            $this->logger->info('Stored combined filter data in the request');
        }

        return $collection;
    }

    public function getProductsIds()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $helper = $objectManager->get(\Gopersonal\Magento\Helper\Data::class);

        return $helper->getProductsIds('layer');
    }

    private function getCombinedFilterData(\Magento\Catalog\Model\ResourceModel\Product\Collection $collection)
    {
        $combinedFilterData = [];
        $this->logger->info('Starting combined filter data calculation');

        $filterableAttributes = $this->filterableAttributeList->getList();

        foreach ($filterableAttributes as $attribute) {
            $attributeCode = $attribute->getAttributeCode();

            if ($attributeCode == 'price') {
                continue;
            }

            $attribute = $collection->getResource()->getAttribute($attributeCode);

            if ($attribute && $attribute->usesSource()) {
                $attributeOptions = $attribute->getSource()->getAllOptions(false);

                $optionMap = [];
                foreach ($attributeOptions as $option) {
                    $optionMap[$option['value']] = [
                        'label' => $option['label'],
                        'count' => 0
                    ];
                }

                foreach ($collection as $product) {
                    $productAttributeValue = $product->getData($attributeCode);

                    if ($productAttributeValue) {
                        $productAttributeValues = explode(',', $productAttributeValue);
                        foreach ($productAttributeValues as $value) {
                            if (isset($optionMap[$value])) {
                                $optionMap[$value]['count']++;
                            }
                        }
                    }
                }

                // Remove options with count 0
                foreach ($optionMap as $key => $data) {
                    if ($data['count'] === 0) {
                        unset($optionMap[$key]);
                    }
                }

                $combinedFilterData[$attributeCode] = $optionMap;
            }
        }

        $this->logger->info('Finished combined filter data calculation');
        return $combinedFilterData;
    }

    private function applyUrlFilters($collection)
    {
        $this->logger->info('Applying URL filters');
        $filterableAttributes = $this->filterableAttributeList->getList();

        foreach ($filterableAttributes as $attribute) {
            $attributeCode = $attribute->getAttributeCode();
            $filterValue = $this->request->getParam($attributeCode);

            if ($filterValue) {
                $this->logger->info("Applying filter: $attributeCode = $filterValue");
                $filterValues = explode(',', $filterValue);
                $this->logger->info("Filter values: " . json_encode($filterValues));

                // Apply filter for each value
                foreach ($filterValues as $value) {
                    $collection->addAttributeToFilter(
                        [
                            ['attribute' => $attributeCode, 'finset' => $value],
                            ['attribute' => $attributeCode, 'eq' => $value]
                        ]
                    );
                }
            }
        }

        // Log the product data to inspect the attributes
        foreach ($collection as $product) {
            $this->logger->info('Product Data: ' . json_encode($product->getData()));
        }
    }
}
