<?php

namespace Gopersonal\Magento\Block\Navigation;

use Magento\Framework\View\Element\Template\Context;
use Magento\LayeredNavigation\Block\Navigation\FilterRenderer as BaseFilterRenderer;
use Psr\Log\LoggerInterface;

class FilterRenderer extends BaseFilterRenderer
{
    protected $logger;

    public function __construct(
        Context $context,
        LoggerInterface $logger,
        array $data = []
    ) {
        $this->logger = $logger;
        parent::__construct($context, $data);
    }

    public function getLogger()
    {
        return $this->logger;
    }

    public function getFilterCounts()
    {
        $filterCounts = $this->getData('filter_counts');
        $this->logger->info('Filter Counts: from Block ' . json_encode($filterCounts));
        return $filterCounts;
    }

    public function getFilterData()
    {
        $filter = $this->getData('filter');
        $this->logger->info('Filter Data: from Block ' . json_encode($filter));
        return $filter;
    }
}
