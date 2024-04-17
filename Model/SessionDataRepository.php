<?php
namespace Gopersonal\Magento\Model;

use Gopersonal\Magento\Api\SessionDataRepositoryInterface;
use Magento\Framework\Session\SessionManagerInterface;

class SessionDataRepository implements SessionDataRepositoryInterface
{
    const SESSION_KEY = 'gopersonal_magento_session_data';

    protected $sessionManager;

    public function __construct(SessionManagerInterface $sessionManager)
    {
        $this->sessionManager = $sessionManager;
    }

    public function storeSessionData($data)
    {
        $this->sessionManager->setData(self::SESSION_KEY, $data);
    }

    public function getSessionData()
    {
        return $this->sessionManager->getData(self::SESSION_KEY);
    }
}