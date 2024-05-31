<?php

namespace Gopersonal\Magento\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Request\Http;
use Psr\Log\LoggerInterface;

class Data extends AbstractHelper
{
    protected $request;
    protected $logger;
    protected $productIds = null;
    protected $isGenerating = false;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        Http $request,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->logger = $logger;
        parent::__construct($context);
    }

    public function getProductsIds($flag = null)
    {
        // Check if the product IDs are already cached in the property
        if ($this->productIds !== null) {
            $this->logger->info('Product IDs retrieved from cache:', ['productIds' => $this->productIds, 'flag' => $flag]);
            return $this->productIds;
        }

        // Check if the generation is already in progress
        if ($this->isGenerating) {
            // Wait until the generation is complete
            while ($this->isGenerating) {
                usleep(10000); // Sleep for 10 milliseconds
            }
            // Retrieve the product IDs after waiting
            $this->logger->info('Product IDs retrieved from cache after waiting:', ['productIds' => $this->productIds, 'flag' => $flag]);
            return $this->productIds;
        }

        // Set the flag to indicate that generation is in progress
        $this->isGenerating = true;

        try {
            $q = $this->request->getParam('q', '');
            if ($q) {
                $this->logger->info('Search query parameter:', ['q' => $q, 'flag' => $flag]);
            }
            $this->productIds = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];
            $this->logger->info('Product IDs generated:', ['productIds' => $this->productIds, 'flag' => $flag]);
        } finally {
            // Release the flag
            $this->isGenerating = false;
        }

        return $this->productIds;
    }
}
