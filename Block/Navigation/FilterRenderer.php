<?php

namespace Gopersonal\Magento\Block\Navigation;

use Magento\LayeredNavigation\Block\Navigation\FilterRenderer as BaseFilterRenderer;
use Psr\Log\LoggerInterface;

class FilterRenderer extends BaseFilterRenderer
{
    protected $logger;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\View\Element\Html\LinkFactory $linkFactory,
        LoggerInterface $logger,
        array $data = []
    ) {
        $this->logger = $logger;
        parent::__construct($context, $linkFactory, $data);
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
