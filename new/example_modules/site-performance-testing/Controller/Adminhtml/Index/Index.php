<?php
namespace MDOQ\SitePerformanceMonitoring\Controller\Adminhtml\Index;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use MDOQ\SitePerformanceMonitoring\Model\Logger;
use Magento\Framework\App\Action\HttpOptionsActionInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
	public function __construct(
		protected Logger $logger,
        protected PageFactory $resultPageFactory,
        Context $context
    ){ 
        parent::__construct($context);
    }

    protected function _isAllowed()
    {
        return true;
    }

	public function execute()
	{
        $this->_view->loadLayout();
        $this->_setActiveMenu('MDOQ_SitePerformanceMonitoring::index');
        $this->_view->getPage()->getConfig()->getTitle()->set(__('Performance Monitoring'));
        $this->_view->renderLayout();
	}
}