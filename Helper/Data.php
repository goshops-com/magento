<?php

namespace Gopersonal\Magento\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Request\Http;
use Psr\Log\LoggerInterface;

class Data extends AbstractHelper
{
    protected $request;
    protected $logger;

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
        // Check if the product IDs are already stored in the request
        if ($this->request->getParam('product_ids') !== null) {
            $productIds = $this->request->getParam('product_ids');
            $this->logger->info('Product IDs retrieved from request:', ['productIds' => $productIds, 'flag' => $flag, 'request_id' => $this->requestId]);
            return $productIds;
        }

        // Set a temporary flag in the request to indicate that generation is in progress
        $this->request->setParam('product_ids_generation_in_progress', true);

        try {
            $q = $this->request->getParam('q', '');
            if ($q) {
                $this->logger->info('Search query parameter:', ['q' => $q, 'flag' => $flag]);
            }
            $productIds = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];
            $this->logger->info('Product IDs generated:', ['productIds' => $productIds, 'flag' => $flag]);

            // Store the generated product IDs in the request
            $this->request->setParam('product_ids', $productIds);
        } finally {
            // Remove the temporary flag
            $this->request->setParam('product_ids_generation_in_progress', null);
        }

        return $productIds;
    }
}
