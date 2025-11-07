<?php
namespace MDOQ\SitePerformanceMonitoring\Controller\Index;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use MDOQ\SitePerformanceMonitoring\Model\Logger;
use Magento\Framework\App\Action\HttpOptionsActionInterface;

class Index implements HttpPostActionInterface, HttpOptionsActionInterface, CsrfAwareActionInterface
{
	public function __construct(
		protected JsonFactory $jsonFactory,
		protected RequestInterface $request,
		protected Logger $logger
    ){ }

	/**
     * Create exception in case CSRF validation failed.
     * Return null if default exception will suffice.
     *
     * @param RequestInterface $request
     *
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException
	{
		return new InvalidRequestException(
			__('CSRF validation failed. Please refresh the page and try again.')
		);
	}

	/**
     * Perform custom request validation.
     * Return null if default validation is needed.
     *
     * @param RequestInterface $request
     *
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
	{
		return true;
	}

	public function execute()
	{
		$result = $this->jsonFactory->create();
		$result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
		$result->setHeader('Access-Control-Allow-Origin', '*', true);

		if($this->request->getMethod() != 'OPTIONS'){
			try{
				$this->logger->log($this->request);
			}catch(\Throwable $e){ }
		}

		// intentionally always return 'ok'
		$result->setData([
			'message' => 'ok',
		]);
		return $result;
	}
}