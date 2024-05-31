<?php
/**
 * @package   Gopersonal_Search
 * @author    Shahid Taj
 */
namespace Gopersonal\Magento\Block;

class Navigation extends \Magento\LayeredNavigation\Block\Navigation
{
	public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
		\Gopersonal\Magento\Model\Layer\Resolver $layerResolver,
		\Magento\Catalog\Model\Layer\FilterList $filterList,
		\Magento\Catalog\Model\Layer\AvailabilityFlagInterface $visibilityFlag,
		array $data = []
	) {
		parent::__construct(
			$context,
			$layerResolver,
			$filterList,
			$visibilityFlag
		);
	}
}
?>
