<?php

namespace MDOQ\SitePerformanceMonitoring\Controller\Adminhtml\Site;

use Magento\Framework\App\Action\HttpGetActionInterface;
use MDOQ\SitePerformanceMonitoring\Controller\Adminhtml\Site as ModelController;
use MDOQ\SitePerformanceMonitoring\Api\SiteRepositoryInterface as ModelRepository;
use Magento\Backend\Model\View\Result\ForwardFactory;

class NewAction extends ModelController implements HttpGetActionInterface
{
    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Registry $coreRegistry
     * @param \Magento\Backend\Model\View\Result\ForwardFactory $resultForwardFactory
     */
    public function __construct(
        ModelRepository $modelRepository,
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Registry $coreRegistry,
        protected ForwardFactory $resultForwardFactory
    ) {
        parent::__construct($modelRepository, $context, $coreRegistry);
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Forward $resultForward */
        $resultForward = $this->resultForwardFactory->create();
        return $resultForward->forward('edit');
    }
}
