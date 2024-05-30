<?php
/**
 * @package   Gopersonal_Search
 * @author    Shahid Taj
 */
namespace Gopersonal\Search\Controller\Index;

class Index extends \Magento\Framework\App\Action\Action
{
	/**
	 * @var \Magento\Framework\View\Result\PageFactory $pageFactory
	 */
	protected $_pageFactory;

	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\View\Result\PageFactory $pageFactory
	){
		$this->_pageFactory = $pageFactory;
		return parent::__construct($context);
	}

	/**
	 * Execute method
	 */
	public function execute()
	{
		return $this->_pageFactory->create();
	}
}
