<?php

namespace MDOQ\SitePerformanceMonitoring\Controller\Adminhtml\Site;

use Magento\Framework\App\Action\HttpGetActionInterface;
use MDOQ\SitePerformanceMonitoring\Controller\Adminhtml\Site as ModelController;
use MDOQ\SitePerformanceMonitoring\Api\SiteRepositoryInterface as ModelRepository;
use Magento\Framework\View\Result\PageFactory;

class Edit extends ModelController implements HttpGetActionInterface
{
    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Registry $coreRegistry
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(
        ModelRepository $modelRepository,
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Registry $coreRegistry,
        protected PageFactory $resultPageFactory
    ) {
        parent::__construct($modelRepository, $context, $coreRegistry);
    }

    /**
     * Edit
     *
     * @return \Magento\Framework\Controller\ResultInterface
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        $model = $this->modelRepository->getNew();

        if ($id) {
            try{
                $model = $this->modelRepository->getById($id);
            }catch(\Magento\Framework\Exception\NoSuchEntityException $e){
                $this->messageManager->addErrorMessage(__('This site no longer exists.'));
                /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        }

        $this->_coreRegistry->register('site', $model);

        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $this->initPage($resultPage)->addBreadcrumb(
            $id ? __('Edit Site') : __('New Site'),
            $id ? __('Edit Site') : __('New Site')
        );
        $resultPage->getConfig()->getTitle()->prepend(__('Sites'));
        $resultPage->getConfig()->getTitle()->prepend($model->getId() ? $model->getTitle() : __('New Site'));
        return $resultPage;
    }
}
