<?php
namespace MDOQ\SitePerformanceMonitoring\Block\Adminhtml\Index;

use Magento\Backend\Block\Template\Context;
use MDOQ\SitePerformanceMonitoring\Model\LogsService;
use Magento\Backend\Model\UrlInterface;

class Content extends \Magento\Backend\Block\Template
{
    protected $_template = 'MDOQ_SitePerformanceMonitoring::index/content.phtml';

    public function __construct(
        protected LogsService $logsService,
        protected UrlInterface $urlBuilder,
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getOptionArray()
    {
        return $this->logsService->getOptionArray();
    }

    public function getReportUrl()
    {
        return $this->urlBuilder->getUrl('performance_monitoring/index/report');
    }
}