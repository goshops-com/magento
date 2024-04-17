<?php
namespace Gopersonal\Magento\Controller;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Gopersonal\Magento\Api\SessionDataRepositoryInterface;

class StoreSessionData extends Action
{
    protected $sessionDataRepository;

    public function __construct(
        Context $context,
        SessionDataRepositoryInterface $sessionDataRepository
    ) {
        parent::__construct($context);
        $this->sessionDataRepository = $sessionDataRepository;
    }

    public function execute()
    {
        $data = $this->getRequest()->getParam('data');
        $this->sessionDataRepository->storeSessionData($data);
        $response = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
        $response->setData(['message' => 'Session data stored successfully']);
        return $response;
    }
}