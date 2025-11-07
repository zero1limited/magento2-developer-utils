<?php
namespace MDOQ\SitePerformanceMonitoring\Controller;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use MDOQ\SitePerformanceMonitoring\Model\Logger;
use Magento\Framework\App\Action\HttpOptionsActionInterface;
use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\RouterInterface;

class Router implements RouterInterface
{
    /**
     * Router constructor.
     *
     * @param ActionFactory $actionFactory
     * @param ResponseInterface $response
     */
    public function __construct(
        protected ActionFactory $actionFactory,
        protected ResponseInterface $response
    ) {
    }

    /**
     * @param RequestInterface $request
     * @return ActionInterface|null
     */
    public function match(RequestInterface $request): ?ActionInterface
    {
        $identifier = trim($request->getPathInfo(), '/');

        if (strpos($identifier, 'performance_monitoring/') === 0) {
            $exploded = explode('/', $identifier);
            $key = $exploded[1] ?? null;
            $request->setModuleName('MDOQ_SitePerformanceMonitoring');
            $request->setControllerName('index');
            $request->setActionName('index');
            $request->setParams([
                'key' => $key,
            ]);
            return $this->actionFactory->create(Forward::class, ['request' => $request]);
        }
        return null;
    }
}