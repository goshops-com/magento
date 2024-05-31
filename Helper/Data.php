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
        if ($this->productIds === null) {
            $q = $this->request->getParam('q', '');
            if ($q) {
                $this->logger->info('Search query parameter:', ['q' => $q]);
            }
            $this->productIds = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];
            $this->logger->info('Product IDs generated:', ['productIds' => $this->productIds]);
        } else {
            $this->logger->info('Product IDs retrieved from cache:', ['productIds' => $this->productIds]);
        }

        return $this->productIds;
    }
}
