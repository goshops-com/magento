<?php
namespace Gopersonal\Magento\Block\Navigation;

use Magento\Framework\View\Element\Template;
use Psr\Log\LoggerInterface;

class FilterRenderer extends \Magento\LayeredNavigation\Block\Navigation\FilterRenderer
{
    protected $logger;

    public function __construct(
        Template\Context $context,
        LoggerInterface $logger,
        array $data = []
    ) {
        $this->logger = $logger;
        parent::__construct($context, $data);
    }

    public function getFilterCounts()
    {
        $filterCounts = $this->getData('filter_counts');
        $this->logger->info('Filter Counts: from Render ' . json_encode($filterCounts));
        return $filterCounts;
    }

    public function getFilterData()
    {
        $filter = $this->getData('filter');
        $this->logger->info('Filter Data: from Render ' . json_encode($filter));
        return $filter;
    }

    public function logFilterItem($filterItem, $filterCount)
    {
        $this->logger->info('Filter Item: ' . json_encode([
            'label' => $filterItem->getLabel(),
            'value' => $filterItem->getValue(),
            'url' => $filterItem->getUrl(),
            'count' => $filterCount
        ]));
    }
}
