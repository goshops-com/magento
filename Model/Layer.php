<?php
/**
 * @package   Gopersonal_Magento
 * @author    Shahid Taj
 */
namespace Gopersonal\Magento\Model;

use Magento\Framework\App\ObjectManager;
use Psr\Log\LoggerInterface;

class Layer extends \Magento\Catalog\Model\Layer
{
    protected $logger;
    protected $filterableAttributeList;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Catalog\Model\Layer\ContextInterface $context,
        LoggerInterface $logger,
        \Magento\Catalog\Model\Layer\Category\FilterableAttributeList $filterableAttributeList,
        array $data = []
    ) {
        $this->logger = $logger;
        $this->filterableAttributeList = $filterableAttributeList;
        parent::__construct($context, $data);
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

        $this->logger->info('Finished getProductCollection method');

        // Calculate filter counts after getting the product collection
        $filterCounts = $this->calculateFilterCounts($collection);
        $this->setData('filter_counts', $filterCounts);

        return $collection;
    }

    public function getProductsIds()
    {
        $objectManager = ObjectManager::getInstance();
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
                $attributeOptions = $attribute->getSource()->getAllOptions();

                // Iterate through each product to collect filter data
                foreach ($collection as $product) {
                    $productAttributeValue = $product->getData($attributeCode);

                    // Find the matching filter option label and ensure it's counted
                    foreach ($attributeOptions as $option) {
                        if ($option['value'] == $productAttributeValue) {
                            if (!isset($filterCounts[$attributeCode][$option['value']])) {
                                $filterCounts[$attributeCode][$option['value']] = 0;
                            }
                            $filterCounts[$attributeCode][$option['value']]++;
                            $this->logger->debug('Filter item "' . $option['label'] . '" (' . $option['value'] . ') has count: ' . $filterCounts[$attributeCode][$option['value']]);
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

        return $filterCounts;
    }
}
