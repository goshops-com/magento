<?php

namespace Gopersonal\Magento\Plugin;

use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface;

class FrontControllerPlugin
{
    protected static $executed = false;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function aroundDispatch(
        FrontControllerInterface $subject,
        \Closure $proceed,
        RequestInterface $request
    ) {
        $this->logger->info('FrontControllerPlugin aroundDispatch called.', [
            'path' => $request->getPathInfo(),
            'executed' => self::$executed,
            'method' => $request->getMethod(),
            'params' => $request->getParams()
        ]);

        // Check if the code has already been executed and if the URL is /search
        if (!self::$executed && $request->getPathInfo() === '/search') {
            self::$executed = true;

            // Log the execution
            $this->logger->info('FrontControllerPlugin executed.', [
                'path' => $request->getPathInfo(),
                'method' => $request->getMethod(),
                'params' => $request->getParams()
            ]);

            // Your custom code here
            // This code will run only once per request
        }

        // Proceed with the normal dispatch process
        return $proceed($request);
    }
}
