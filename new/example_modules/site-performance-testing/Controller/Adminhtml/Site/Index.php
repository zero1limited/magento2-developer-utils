<?php

namespace MDOQ\SitePerformanceMonitoring\Controller\Adminhtml\Site;

use Magento\Framework\App\Action\HttpGetActionInterface;
use MDOQ\SitePerformanceMonitoring\Controller\Adminhtml\Site as ModelController;
use MDOQ\SitePerformanceMonitoring\Api\SiteRepositoryInterface as ModelRepository;

/**
 * Index action.
 */
class Index extends ModelController implements HttpGetActionInterface
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
        protected \Magento\Framework\View\Result\PageFactory $resultPageFactory
    ) {
        parent::__construct($modelRepository, $context, $coreRegistry);
    }

    /**
     * Index action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $this->initPage($resultPage)->getConfig()->getTitle()->prepend(__('Sites'));

        $dataPersistor = $this->_objectManager->get(\Magento\Framework\App\Request\DataPersistorInterface::class);
        $dataPersistor->clear('mdoq_siteperformancemonitoring_site');

        return $resultPage;
    }
}
