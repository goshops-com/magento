<?php
namespace Gopersonal\Magento\Api;

interface SessionDataRepositoryInterface
{
    /**
     * Store session data
     *
     * @param string $data
     * @return void
     */
    public function storeSessionData($data);

    /**
     * Get session data
     *
     * @return string|null
     */
    public function getSessionData();
}