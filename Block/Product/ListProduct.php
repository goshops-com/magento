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
}
