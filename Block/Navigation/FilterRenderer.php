<?php

namespace Gopersonal\Magento\Block\Navigation;

use Magento\Framework\View\Element\Template\Context;
use Magento\LayeredNavigation\Block\Navigation\FilterRenderer as BaseFilterRenderer;
use Psr\Log\LoggerInterface;

class FilterRenderer extends BaseFilterRenderer
{
    protected $logger;
    protected $request;

    public function __construct(
        Context $context,
        LoggerInterface $logger,
        array $data = []
    ) {
        $this->logger = $logger;
        $this->request = $context->getRequest();
        parent::__construct($context, $data);
    }

    public function getLogger()
    {
        return $this->logger;
    }

    public function getFilterCounts()
    {
        $filterCounts = $this->request->getParam('filter_counts');
        $this->logger->info('Filter Counts: from Block ' . json_encode($filterCounts));
        return $filterCounts;
    }

    public function getFilterData()
    {
        $filter = $this->request->getParam('filter_data');
        $this->logger->info('Filter Data: from Block ' . json_encode($filter));
        return $filter;
    }
}
