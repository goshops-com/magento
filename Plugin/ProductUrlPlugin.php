<?php
namespace Gopersonal\Magento\Plugin;

class ProductUrlPlugin
{
    public function afterGetProductUrl(\Magento\Catalog\Model\Product $subject, $result)
    {
        // Parse the URL

        $separator = (strpos($result, '?') !== false) ? '&' : '?';
        return $result . $separator . 'my_param=value';

        // $urlParts = parse_url($result);
        
        // if (isset($urlParts['path'])) {
        //     // Remove any double slashes in the path
        //     $urlParts['path'] = preg_replace('#/{2,}#', '/', $urlParts['path']);
        // }
        
        // // Rebuild the URL
        // $scheme   = isset($urlParts['scheme']) ? $urlParts['scheme'] . '://' : '';
        // $host     = isset($urlParts['host']) ? $urlParts['host'] : '';
        // $port     = isset($urlParts['port']) ? ':' . $urlParts['port'] : '';
        // $user     = isset($urlParts['user']) ? $urlParts['user'] : '';
        // $pass     = isset($urlParts['pass']) ? ':' . $urlParts['pass']  : '';
        // $pass     = ($user || $pass) ? "$pass@" : '';
        // $path     = isset($urlParts['path']) ? $urlParts['path'] : '';
        // $query    = isset($urlParts['query']) ? '?' . $urlParts['query'] : '';
        // $fragment = isset($urlParts['fragment']) ? '#' . $urlParts['fragment'] : '';
        
        // $cleanedUrl = $scheme . $user . $pass . $host . $port . $path . $query . $fragment;
        
        // return $cleanedUrl;
    }

    private function addParamToUrl($url, $paramName, $paramValue)
    {
        $separator = (strpos($url, '?') !== false) ? '&' : '?';
        return $url . $separator . $paramName . '=' . urlencode($paramValue);
    }
}