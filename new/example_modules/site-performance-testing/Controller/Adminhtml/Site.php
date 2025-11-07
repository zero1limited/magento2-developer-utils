<?php

namespace MDOQ\SitePerformanceMonitoring\Controller\Adminhtml;

use MDOQ\SitePerformanceMonitoring\Api\SiteRepositoryInterface as ModelRepository;

abstract class Site extends \Magento\Backend\App\Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'MDOQ_SitePerformanceMonitoring::site';

    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Registry $coreRegistry
     */
    public function __construct(
        protected ModelRepository $modelRepository,
        \Magento\Backend\App\Action\Context $context, 
        \Magento\Framework\Registry $coreRegistry
    ) {
        $this->_coreRegistry = $coreRegistry;
        parent::__construct($context);
    }

    /**
     * Init page
     *
     * @param \Magento\Backend\Model\View\Result\Page $resultPage
     * @return \Magento\Backend\Model\View\Result\Page
     */
    protected function initPage($resultPage)
    {
        $resultPage->setActiveMenu('MDOQ_SitePerformanceMonitoring::site')
            ->addBreadcrumb(__('MDOQ SitePerformanceMonitoring'), __('MDOQ SitePerformanceMonitoring'))
            ->addBreadcrumb(__('Site'), __('Site'));
        return $resultPage;
    }
}
