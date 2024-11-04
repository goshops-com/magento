<?php
namespace Gopersonal\Magento\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;
use Psr\Log\LoggerInterface;

class FulltextCollectionPlugin
{
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function beforeLoad(Collection $subject, $printQuery = false, $logQuery = false)
    {
        var_dump("BEFORE COLLECTION LOAD");
        var_dump("Collection Query:", $subject->getSelect()->__toString());
        return [$printQuery, $logQuery];
    }

    public function afterLoad(Collection $subject, $result)
    {
        var_dump("AFTER COLLECTION LOAD");
        var_dump("Collection Size:", $subject->getSize());
        return $result;
    }
}