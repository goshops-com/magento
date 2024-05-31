<?php

namespace Gopersonal\Magento\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Request\Http;
use Psr\Log\LoggerInterface;
use Magento\Framework\Registry;

class Data extends AbstractHelper
{
    protected $request;
    protected $logger;
    protected $registry;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        Http $request,
        LoggerInterface $logger,
        Registry $registry
    ) {
        parent::__construct($context);
        $this->request = $request;
        $this->logger = $logger;
        $this->registry = $registry;
    }

    public function getProductsIds($flag = null)
    {
        // Check if the product IDs are already stored in the registry
        if ($this->registry->registry('product_ids') !== null) {
            $productIds = $this->registry->registry('product_ids');
            $this->logger->info('Product IDs retrieved from registry:', ['productIds' => $productIds, 'flag' => $flag]);
            return $productIds;
        }

        // Check if the generation is already in progress
        if ($this->registry->registry('product_ids_generation_in_progress')) {
            // Wait until the generation is complete
            while ($this->registry->registry('product_ids_generation_in_progress')) {
                usleep(10000); // Sleep for 10 milliseconds
            }
            // Retrieve the product IDs after waiting
            $productIds = $this->registry->registry('product_ids');
            $this->logger->info('Product IDs retrieved from registry after waiting:', ['productIds' => $productIds, 'flag' => $flag]);
            return $productIds;
        }

        // Set a flag in the registry to indicate that generation is in progress
        $this->registry->register('product_ids_generation_in_progress', true);

        try {
            $q = $this->request->getParam('q', '');
            if ($q) {
                $this->logger->info('Search query parameter:', ['q' => $q, 'flag' => $flag]);
            }
            $productIds = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];
            $this->logger->info('Product IDs generated:', ['productIds' => $productIds, 'flag' => $flag]);

            // Store the generated product IDs in the registry
            $this->registry->register('product_ids', $productIds);
        } finally {
            // Remove the flag from the registry
            $this->registry->unregister('product_ids_generation_in_progress');
        }

        return $productIds;
    }
}
