<?php

namespace Gopersonal\Magento\Model;

use Magento\Framework\Search\AdapterInterface;
use Magento\Framework\Search\Response\QueryResponse;

class CustomAdapter implements AdapterInterface
{
    /**
     * @var array
     */
    private $fixedProductIds = [1556];

    /**
     * @inheritDoc
     */
    public function query(\Magento\Framework\Search\RequestInterface $request)
    {
        // Here we create a fixed response with the product IDs we want to return.
        $response = new QueryResponse(
            ['documents' => $this->getDocuments()],
            $this->getTotalCount()
        );

        return $response;
    }

    /**
     * Get a fixed list of documents.
     *
     * @return array
     */
    private function getDocuments()
    {
        $documents = [];
        foreach ($this->fixedProductIds as $productId) {
            $documents[] = ['entity_id' => $productId];
        }
        return $documents;
    }

    /**
     * Get the total count of fixed products.
     *
     * @return int
     */
    private function getTotalCount()
    {
        return count($this->fixedProductIds);
    }
}
