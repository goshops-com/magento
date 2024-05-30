<?php

namespace Gopersonal\Magento\Plugin;

use Psr\Log\LoggerInterface;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Framework\App\RequestInterface;

class LayeredNavigationPlugin
{
    protected $layerResolver;
    protected $request;
    protected $logger;

    public function __construct(LayerResolver $layerResolver, RequestInterface $request, LoggerInterface $logger)
    {
        $this->layerResolver = $layerResolver;
        $this->request = $request;
        $this->logger = $logger;
    }

    public function aroundGetProductCollection($subject, callable $proceed)
    {
        $this->logger->info('LayeredNavigationPlugin: aroundGetProductCollection called');
        
        // Proceed with the original method
        $productCollection = $proceed();
        $this->logger->info('LayeredNavigationPlugin: original getProductCollection called');

        if ($this->isCategoryPage()) {
            $this->logger->info('LayeredNavigationPlugin: isCategoryPage is true');
            
            // Ensure the layered navigation works on category pages
            $layer = $this->layerResolver->get();
            $this->logger->info('LayeredNavigationPlugin: LayerResolver get called');

            $layer->apply();
            $this->logger->info('LayeredNavigationPlugin: Layer apply called');

            $productCollection = $layer->getProductCollection();
            $this->logger->info('LayeredNavigationPlugin: Layer getProductCollection called');
        }

        return $productCollection;
    }

    protected function isCategoryPage()
    {
        $isCategoryPage = $this->request->getFullActionName() === 'catalog_category_view';
        $this->logger->info('LayeredNavigationPlugin: isCategoryPage: ' . ($isCategoryPage ? 'true' : 'false'));
        return $isCategoryPage;
    }
}
