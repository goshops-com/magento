<?php
namespace Gopersonal\Magento\Model\Search;

use Magento\Framework\Search\EntityMetadata;
use Magento\Framework\Search\Request\BucketInterface;
use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\Response\QueryResponse;
use Magento\Framework\Search\Response\Aggregation;
use Magento\Framework\Api\Search\SearchResultInterface;

class CustomSearchResultProvider implements \Magento\Framework\Search\SearchResponseBuilder
{
    private $entityMetadata;

    public function __construct(EntityMetadata $entityMetadata)
    {
        $this->entityMetadata = $entityMetadata;
    }

    public function build(RequestInterface $request, array $rawSearchResults)
    {
        // Always return our hardcoded product
        $documents = [
            [
                $this->entityMetadata->getEntityId() => 2040,
                'score' => 1
            ]
        ];

        return new QueryResponse(
            $documents,
            $this->buildAggregations($request, $rawSearchResults)
        );
    }

    private function buildAggregations(RequestInterface $request, array $rawSearchResults)
    {
        $buckets = [];
        return new Aggregation($buckets);
    }
}