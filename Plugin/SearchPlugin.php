// app/code/Gopersonal/Magento/Plugin/SearchPlugin.php
<?php
namespace Gopersonal\Magento\Plugin;

class SearchPlugin
{
    public function beforeLoad(\Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection $collection)
    {
        die('Search plugin called!');
        return null;
    }
}