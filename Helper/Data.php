<?php

namespace Gopersonal\Magento\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Request\Http;
use Psr\Log\LoggerInterface;

class Data extends AbstractHelper
{
    protected $request;
    protected $logger;
    protected $productIds;
    protected static $isGenerating = [];

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        Http $request,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->logger = $logger;
        $this->productIds = null;
        parent::__construct($context);
    }

    public function getProductsIds()
    {
        $requestHash = spl_object_hash($this->request);

        // Check if the product IDs have already been generated
        if ($this->productIds !== null) {
            $this->logger->info('Product IDs retrieved from cache:', ['productIds' => $this->productIds]);
            return $this->productIds;
        }

        // Check if another process is already generating the product IDs
        if (isset(self::$isGenerating[$requestHash])) {
            // Wait until the product IDs have been generated
            while (isset(self::$isGenerating[$requestHash])) {
                usleep(10000); // Sleep for 10 milliseconds
            }
            $this->logger->info('Product IDs retrieved from cache after waiting:', ['productIds' => $this->productIds]);
            return $this->productIds;
        }

        // Set the semaphore to indicate that generation is in progress
        self::$isGenerating[$requestHash] = true;

        try {
            $q = $this->request->getParam('q', '');
            if ($q) {
                $this->logger->info('Search query parameter:', ['q' => $q]);
            }
            $this->productIds = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];
            $this->logger->info('Product IDs generated:', ['productIds' => $this->productIds]);
        } finally {
            // Release the semaphore
            unset(self::$isGenerating[$requestHash]);
        }

        return $this->productIds;
    }
}
