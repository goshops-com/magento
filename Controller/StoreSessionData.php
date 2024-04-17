<?php
namespace Gopersonal\Magento\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Gopersonal\Magento\Model\SessionDataRepository;

class StoreSessionData extends Action
{
    protected $sessionDataRepository;
    protected $resultJsonFactory;

    public function __construct(
        Context $context,
        SessionDataRepository $sessionDataRepository,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->sessionDataRepository = $sessionDataRepository;
        $this->resultJsonFactory = $resultJsonFactory;
    }

    public function execute()
    {
        $data = $this->getRequest()->getParam('data');
        $this->sessionDataRepository->storeSessionData($data);

        $resultJson = $this->resultJsonFactory->create();
        $resultJson->setData(['message' => 'Session data stored successfully']);

        return $resultJson;
    }
}