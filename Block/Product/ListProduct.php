<?php
namespace Gopersonal\Magento\Block\Product;

use Magento\Catalog\Model\Product;
use Psr\Log\LoggerInterface;

class ListProduct extends \Magento\Catalog\Block\Product\ListProduct
{
    protected $logger;

    public function __construct(
        \Magento\Catalog\Block\Product\Context $context,
        \Magento\Framework\Data\Helper\PostHelper $postDataHelper,
        \Gopersonal\Magento\Model\Layer\Resolver $layerResolver,
        \Magento\Catalog\Api\CategoryRepositoryInterface $categoryRepository,
        \Magento\Framework\Url\Helper\Data $urlHelper,
        LoggerInterface $logger,
        array $data = []
    ) {
        $this->logger = $logger;
        parent::__construct(
            $context,
            $postDataHelper,
            $layerResolver,
            $categoryRepository,
            $urlHelper,
            $data
        );
    }

    /**
     * Get Product URL
     *
     * @param Product $product
     * @param array $additional
     * @return string
     */
    public function getProductUrl($product, $additional = [])
    {
        $originalUrl = parent::getProductUrl($product, $additional);
        
        // Normalize the URL
        $modifiedUrl = $this->normalizeUrl($originalUrl);

        // Log the original and modified URLs
        $this->logUrls($originalUrl, $modifiedUrl);

        return $modifiedUrl;
    }

    /**
     * Normalize the URL to ensure no double slashes after the protocol
     *
     * @param string $url
     * @return string
     */
    protected function normalizeUrl($url)
    {
        // Parse the URL to extract components
        $parsedUrl = parse_url($url);

        // Reconstruct the URL, ensuring no double slashes
        $scheme = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] . '://' : '';
        $host = isset($parsedUrl['host']) ? $parsedUrl['host'] : '';
        $path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';

        // Normalize path to remove double slashes
        $path = preg_replace('#/+#', '/', $path);

        // Construct the normalized URL
        $normalizedUrl = $scheme . $host . $path;

        return $normalizedUrl;
    }

    /**
     * Log the original and modified URLs
     *
     * @param string $originalUrl
     * @param string $modifiedUrl
     * @return void
     */
    protected function logUrls($originalUrl, $modifiedUrl)
    {
        $this->logger->debug('Original Product URL: ' . $originalUrl);
        $this->logger->debug('Modified Product URL: ' . $modifiedUrl);
    }
}
