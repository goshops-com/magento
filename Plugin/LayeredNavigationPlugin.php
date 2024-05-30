<?php

namespace Gopersonal\Magento\Plugin;

use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Framework\App\RequestInterface;

class LayeredNavigationPlugin
{
    protected $layerResolver;
    protected $request;

    public function __construct(LayerResolver $layerResolver, RequestInterface $request)
    {
        $this->layerResolver = $layerResolver;
        $this->request = $request;
    }

    public function aroundGetProductCollection($subject, callable $proceed)
    {
        // Proceed with the original method
        $productCollection = $proceed();

        if ($this->isCategoryPage()) {
            // Ensure the layered navigation works on category pages
            $layer = $this->layerResolver->get();
            $layer->apply();
            $productCollection = $layer->getProductCollection();
        }

        return $productCollection;
    }

    protected function isCategoryPage()
    {
        return $this->request->getFullActionName() === 'catalog_category_view';
    }
}
