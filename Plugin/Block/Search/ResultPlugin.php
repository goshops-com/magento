<?php
namespace Gopersonal\Magento\Plugin\Block\Search;

class ResultPlugin
{
    public function aroundGetCacheLifetime($subject, $proceed)
    {
        // Disable cache for this block by returning 0
        return 0;
    }

    public function aroundGetCacheKeyInfo($subject, $proceed)
    {
        // Return an empty array to effectively disable caching
        return [];
    }
}
