<?php

namespace MDOQ\SitePerformanceMonitoring\Controller\Adminhtml\Site;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use MDOQ\SitePerformanceMonitoring\Controller\Adminhtml\Site as ModelController;
use MDOQ\SitePerformanceMonitoring\Api\SiteRepositoryInterface as ModelRepository;

class Save extends ModelController implements HttpPostActionInterface
{
    /**
     * @param Context $context
     * @param Registry $coreRegistry
     * @param DataPersistorInterface $dataPersistor
     */
    public function __construct(
        ModelRepository $modelRepository,
        Context $context,
        Registry $coreRegistry,
        protected DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($modelRepository, $context, $coreRegistry);
    }

    /**
     * Save action
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();
        if ($data) {
            if(isset($data['id'])) {
                unset($data['id']);
            }
            $model = $this->modelRepository->getNew();
            $id = $this->getRequest()->getParam('id');
            if ($id) {
                try {
                    $model = $this->modelRepository->getById($id);
                } catch (LocalizedException $e) {
                    $this->messageManager->addErrorMessage(__('This site no longer exists.'));
                    return $resultRedirect->setPath('*/*/');
                }
            }

            $model->setData($data);

            try {
                $model = $this->modelRepository->save($model);
                $this->messageManager->addSuccessMessage(__('You saved the site.'));
                $this->dataPersistor->clear('mdoq_siteperformancemonitoring_site');
                return $this->processModelReturn($model, $data, $resultRedirect);
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the site.'));
            }

            $this->dataPersistor->set('mdoq_siteperformancemonitoring_site', $data);
            return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
        }
        return $resultRedirect->setPath('*/*/');
    }

    /**
     * Process and set the model return
     *
     * @param array $data
     * @param \Magento\Framework\Controller\ResultInterface $resultRedirect
     * @return \Magento\Framework\Controller\ResultInterface
     */
    private function processModelReturn($model, $data, $resultRedirect)
    {
        $redirect = $data['back'] ?? 'close';

        if ($redirect ==='continue') {
            $resultRedirect->setPath('*/*/edit', ['id' => $model->getId()]);
        } elseif ($redirect === 'close') {
            $resultRedirect->setPath('*/*/');
        } elseif ($redirect === 'duplicate') {
            $duplicateModel = $this->modelRepository->getNew()
                ->setData($data);
            $duplicateModel->setId(null);
            $this->modelRepository->save($duplicateModel);
            $id = $duplicateModel->getId();
            $this->messageManager->addSuccessMessage(__('You duplicated the site.'));
            $this->dataPersistor->set('mdoq_siteperformancemonitoring_site', $data);
            $resultRedirect->setPath('*/*/edit', ['id' => $id]);
        }
        return $resultRedirect;
    }
}
