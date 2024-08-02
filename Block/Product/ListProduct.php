<?php
namespace Gopersonal\Magento\Block\Product;

use Magento\Catalog\Model\Product;
use Psr\Log\LoggerInterface;

class ListProduct extends \Magento\Catalog\Block\Product\ListProduct
{
    protected $logger;

    public function __construct(
        \Magento\Catalog\Block\Product\Context $context,
        \Magento\Framework\Data\Helper\PostHelper $postDataHelper,
        \Gopersonal\Magento\Model\Layer\Resolver $layerResolver,
        \Magento\Catalog\Api\CategoryRepositoryInterface $categoryRepository,
        \Magento\Framework\Url\Helper\Data $urlHelper,
        LoggerInterface $logger,
        array $data = []
    ) {
        $this->logger = $logger;
        parent::__construct(
            $context,
            $postDataHelper,
            $layerResolver,
            $categoryRepository,
            $urlHelper,
            $data
        );
    }

    public function getProductUrl($product, $additional = [])
    {
        $url = parent::getProductUrl($product, $additional);
        return $this->addParamToUrl($url, 'my_param', 'value');
    }

    private function addParamToUrl($url, $paramName, $paramValue)
    {
        $separator = (strpos($url, '?') !== false) ? '&' : '?';
        return $url . $separator . $paramName . '=' . urlencode($paramValue);
    }
}
