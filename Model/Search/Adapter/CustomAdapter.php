<?php

namespace Gopersonal\Magento\Model\Search\Adapter;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Search\Adapter\Mysql\Aggregation\Builder as AggregationBuilder;
use Magento\Framework\Search\Adapter\Mysql\TemporaryStorageFactory;
use Magento\Framework\Search\AdapterInterface;
use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\ResponseFactory;

class CustomAdapter extends \Magento\Framework\Search\Adapter\Mysql\Adapter
{
    /**
     * @param RequestInterface $request
     * @return \Magento\Framework\Search\ResponseInterface
     */
    public function query(RequestInterface $request)
    {
        // Hardcoded product IDs
        $productIds = [1556]; // Replace with your actual product IDs

        // Create a temporary table with the hardcoded product IDs
        $temporaryStorage = $this->temporaryStorageFactory->create();
        $table = $temporaryStorage->createFromArray($productIds);

        $documents = $this->getDocuments($table);

        $aggregations = $this->aggregationBuilder->build($request, $table, $documents);
        $response = [
            'documents' => $documents,
            'aggregations' => $aggregations,
        ];
        return $this->responseFactory->create($response);
    }

    /**
     * @param array $productIds
     * @return Table
     * @throws \Zend_Db_Exception
     */
    private function createTemporaryTableWithProductIds(array $productIds)
    {
        $connection = $this->getConnection();
        $tableName = $this->resource->getTableName('search_tmp_' . uniqid());
        $connection->createTemporaryTable(
            $tableName,
            [
                'entity_id' => [
                    'type' => Table::TYPE_INTEGER,
                    'nullable' => false,
                    'primary' => true,
                    'auto_increment' => false,
                    'unsigned' => true,
                ],
                'score' => [
                    'type' => Table::TYPE_FLOAT,
                    'nullable' => false,
                    'default' => '0.0',
                ],
            ]
        );

        $data = [];
        foreach ($productIds as $productId) {
            $data[] = ['entity_id' => $productId, 'score' => 1.0];
        }

        $connection->insertMultiple($tableName, $data);

        return $this->resource->getConnection()->describeTable($tableName);
    }
}
