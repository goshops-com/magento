<?php

namespace Gopersonal\Magento\Plugin;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Psr\Log\LoggerInterface;

class SearchCriteriaBuilderPlugin
{
    protected $logger;

    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    public function aroundCreate(
        SearchCriteriaBuilder $subject,
        callable $proceed
    ) {
        $this->logger->info('SearchCriteriaBuilderPlugin aroundCreate called.');

        // List of product IDs to search
        $productIds = [1556]; // Replace with your dynamic IDs

        $subject->addFilter('entity_id', $productIds, 'in');

        return $proceed();
    }
}
