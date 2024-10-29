<?php
namespace Gopersonal\Magento\SearchAdapter;

use Magento\Framework\Search\AdapterInterface;
use Magento\Framework\Api\Search\SearchResultInterfaceFactory;
use Magento\Framework\Api\Search\DocumentFactory;

class CustomAdapter implements AdapterInterface
{
    protected $searchResultFactory;
    protected $documentFactory;
    private $productIds = [2040];

    public function __construct(
        SearchResultInterfaceFactory $searchResultFactory,
        DocumentFactory $documentFactory
    ) {
        $this->searchResultFactory = $searchResultFactory;
        $this->documentFactory = $documentFactory;
    }

    public function query(\Magento\Framework\Search\RequestInterface $request)
    {
        $documents = [];
        foreach ($this->productIds as $productId) {
            $documents[] = $this->documentFactory->create([
                'id' => $productId,
                'fields' => ['entity_id' => $productId],
            ]);
        }

        $searchResult = $this->searchResultFactory->create();
        $searchResult->setItems($documents);
        $searchResult->setTotalCount(count($documents));

        return $searchResult;
    }
}
