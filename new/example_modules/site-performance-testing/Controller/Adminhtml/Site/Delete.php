<?php

namespace MDOQ\SitePerformanceMonitoring\Controller\Adminhtml\Site;

use Magento\Framework\App\Action\HttpPostActionInterface;
use MDOQ\SitePerformanceMonitoring\Controller\Adminhtml\Site as ModelController;

class Delete extends ModelController implements HttpPostActionInterface
{
    /**
     * Delete action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = $this->getRequest()->getParam('id');
        if ($id) {
            try {
                $model = $this->modelRepository->getById($id);
                $this->modelRepository->delete($model);
                $this->messageManager->addSuccessMessage(__('You deleted the site.'));
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
            }
        }
        $this->messageManager->addErrorMessage(__('We can\'t find a site to delete.'));
        return $resultRedirect->setPath('*/*/');
    }
}