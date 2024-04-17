<?php
namespace Gopersonal\Magento\Observer;

use Magento\Framework\Event\ObserverInterface;
use Gopersonal\Magento\Model\SessionDataRepository;
use Psr\Log\LoggerInterface;

class LogSessionData implements ObserverInterface
{
    protected $sessionDataRepository;
    protected $logger;

    public function __construct(
        SessionDataRepository $sessionDataRepository,
        LoggerInterface $logger
    ) {
        $this->sessionDataRepository = $sessionDataRepository;
        $this->logger = $logger;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $sessionData = $this->sessionDataRepository->getSessionData();
        if ($sessionData) {
            $this->logger->info('Session Data: ' . $sessionData);
        }
    }
}