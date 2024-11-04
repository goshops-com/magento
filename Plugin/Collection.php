<?php
namespace Gopersonal\Magento\Plugin;

class Collection extends \Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection
{
    public function getFoundIds()
    {
        file_put_contents('/var/www/html/var/log/search_debug.log', 
            "GetFoundIds called with query: " . $this->queryText . "\n", 
            FILE_APPEND);

        if ($this->queryText) {
            return [2040];
        }
        
        return parent::getFoundIds();
    }
}