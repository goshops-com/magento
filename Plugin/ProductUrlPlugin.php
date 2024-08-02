<?php
namespace Gopersonal\Magento\Plugin;

class ProductUrlPlugin
{
    public function afterGetProductUrl(\Magento\Catalog\Model\Product $subject, $result)
    {
        $separator = (strpos($result, '?') !== false) ? '&' : '?';
        return $result . $separator . 'my_param=value';
    }
}