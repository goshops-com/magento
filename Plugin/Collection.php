<?php
namespace Gopersonal\Magento\Plugin;

class Collection extends \Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection
{
    public function getFoundIds()
    {
        return [2040];
    }
}