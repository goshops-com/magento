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
        print_r('CustomAdapter query called');
        
        // Here we create a fixed response with the product IDs we want to return.
        $response = new QueryResponse(
            ['documents' => $this->getDocuments()],
            $this->getTotalCount()
        );

        print_r($response); // Print the response to see the output

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
        foreach ($this->.fixedProductIds as $productId) {
            $documents[] = ['entity_id' => $productId];
        }
        print_r($documents); // Print the documents to see the output
        return $documents;
    }

    /**
     * Get the total count of fixed products.
     *
     * @return int
     */
    private function getTotalCount()
    {
        $count = count($this->fixedProductIds);
        print_r($count); // Print the count to see the output
        return $count;
    }
}
