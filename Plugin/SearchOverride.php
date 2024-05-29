<?php

namespace Gopersonal\Magento\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as SearchCollection;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class SearchOverride
{
    protected $resourceConnection;
    protected $request;
    protected $scopeConfig;
    protected $logger;

    public function __construct(
        ResourceConnection $resourceConnection,
        Http $request,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->request = $request;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    public function aroundLoad(
        SearchCollection $subject,
        \Closure $proceed,
        $printQuery = false,
        $logQuery = false
    ) {
        $searchQuery = $this->request->getParam('q'); // Get the search query parameter
        $isEnabled = $this->scopeConfig->getValue(
            'gopersonal/general/gopersonal_has_search',
            ScopeInterface::SCOPE_STORE
        );

        $this->logger->info('Search query: ' . $searchQuery);
        $this->logger->info('Plugin enabled: ' . ($isEnabled === 'YES' ? 'YES' : 'NO'));

        if ($searchQuery && $isEnabled === 'YES') {
            $fixedProductIds = [1556]; // Your array of fixed product IDs (or fetch dynamically)
            $this->logger->info('Fixed product IDs: ' . implode(',', $fixedProductIds));

            if (!empty($fixedProductIds)) {
                $select = $subject->getSelect();
                $originalSelect = clone $select;
                
                $this->logger->info('Original query: ' . $originalSelect->__toString());

                // Create a fixed product select
                $fixedProductSelect = $this->resourceConnection->getConnection()->select()
                    ->from(['e' => $subject->getMainTable()], [
                        'entity_id',
                        'attribute_set_id',
                        'type_id',
                        'sku',
                        'has_options',
                        'required_options',
                        'created_at',
                        'updated_at'
                    ])
                    ->where('e.entity_id IN (?)', $fixedProductIds);
                
                $this->logger->info('Fixed product query: ' . $fixedProductSelect->__toString());

                // Create a union select
                $unionSelect = $this->resourceConnection->getConnection()->select()->union([
                    $originalSelect->reset(\Zend_Db_Select::COLUMNS)->columns([
                        'entity_id',
                        'attribute_set_id',
                        'type_id',
                        'sku',
                        'has_options',
                        'required_options',
                        'created_at',
                        'updated_at'
                    ]),
                    $fixedProductSelect
                ]);

                $this->logger->info('Union query: ' . $unionSelect->__toString());

                // Set the union select as the main select
                $subject->getSelect()->reset();
                $subject->getSelect()->from(['main_table' => new \Zend_Db_Expr('(' . $unionSelect . ')')]);

                $this->logger->info('Final executed query: ' . $subject->getSelect()->__toString() . ' URL: ' . $this->request->getUriString());
            }
        }

        return $proceed($printQuery, $logQuery); // Let the original load method proceed with the modified query
    }
}
