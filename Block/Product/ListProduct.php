<?php
/**
 * @package   Gopersonal_Magento
 * @author    Shahid Taj
 */
namespace Gopersonal\Magento\Block\Product;

class ListProduct extends \Magento\Catalog\Block\Product\ListProduct
{
	public function __construct(
		\Magento\Catalog\Block\Product\Context $context,
		\Magento\Framework\Data\Helper\PostHelper $postDataHelper,
		\Gopersonal\Magento\Model\Layer\Resolver $layerResolver,
		\Magento\Catalog\Api\CategoryRepositoryInterface $categoryRepository,
		\Magento\Framework\Url\Helper\Data $urlHelper,
		array $data = []
	) {
		parent::__construct(
			$context,
			$postDataHelper,
			$layerResolver,
			$categoryRepository,
			$urlHelper,
			$data
		);
	}

	public function getProductUrl($product)
    {
        $url = $product->getProductUrl();
        
        // Normalize the URL
        $url = $this->normalizeUrl($url);

        return $url;
    }

    /**
     * Normalize the URL to ensure single leading slash and no trailing slashes
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
}
