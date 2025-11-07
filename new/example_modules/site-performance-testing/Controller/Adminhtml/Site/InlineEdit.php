<?php

namespace MDOQ\SitePerformanceMonitoring\Controller\Adminhtml\Site;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use MDOQ\SitePerformanceMonitoring\Api\SiteRepositoryInterface as ModelRepository;
use MDOQ\SitePerformanceMonitoring\Api\Data\SiteInterface as ModelInterface;

class InlineEdit extends \Magento\Backend\App\Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'MDOQ_SitePerformanceMonitoring::site';

    public function __construct(
        Context $context,
        protected ModelRepository $modelRepository,
        protected JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->jsonFactory->create();
        $error = false;
        $messages = [];

        if ($this->getRequest()->getParam('isAjax')) {
            $postItems = $this->getRequest()->getParam('items', []);
            if (!count($postItems)) {
                $messages[] = __('Please correct the data sent.');
                $error = true;
            } else {
                foreach (array_keys($postItems) as $modelId) {
                    $model = $this->modelRepository->getById($modelId);
                    try {
                        $model->setData(array_merge($model->getData(), $postItems[$modelId]));
                        $this->modelRepository->save($model);
                    } catch (\Exception $e) {
                        $messages[] = $this->getErrorWithModelId(
                            $model,
                            __($e->getMessage())
                        );
                        $error = true;
                    }
                }
            }
        }

        return $resultJson->setData([
            'messages' => $messages,
            'error' => $error
        ]);
    }

    /**
     * @param ModelInterface $model
     * @param string $errorText
     * @return string
     */
    protected function getErrorWithModelId(ModelInterface $model, $errorText)
    {
        return '[Site ID: ' . $model->getId() . '] ' . $errorText;
    }
}
