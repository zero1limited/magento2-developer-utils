<?php
namespace MDOQ\SitePerformanceMonitoring\Block\Adminhtml\Index;

use Magento\Backend\Block\Template\Context;
use MDOQ\SitePerformanceMonitoring\Model\LogsService;
use Magento\Backend\Model\UrlInterface;

class Report extends \Magento\Backend\Block\Template
{
    protected $_template = 'MDOQ_SitePerformanceMonitoring::index/report.phtml';

    public function __construct(
        protected LogsService $logsService,
        protected UrlInterface $urlBuilder,
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getBackUrl()
    {
        return $this->urlBuilder->getUrl('performance_monitoring/index/index');
    }

    public function getDomain()
    {
        return $this->getData('domain');
    }

    public function getMonth()
    {
        return $this->getData('month');
    }

    public function getCachedVsNonCachedByDay()
    {
        return $this->logsService->getCachedVsNonCachedByDay($this->getDomain(), $this->getMonth());
    }

    public function getRequestTimeSplits()
    {
        return $this->logsService->getRequestTimeSplits($this->getDomain(), $this->getMonth());
    }

    public function getResponseTimeVsRequestCount()
    {
        return $this->logsService->getResponseTimeVsRequestCount($this->getDomain(), $this->getMonth());
    }

    public function getRequestBreakdown()
    {
        return $this->logsService->getRequestBreakdown($this->getDomain(), $this->getMonth());
    }

    public function getCachedVsNonCached()
    {
        return $this->logsService->getCachedVsNonCached($this->getDomain(), $this->getMonth());
    }

    public function getSlowestAverageResponseTime()
    {
        return $this->logsService->getSlowestAverageResponseTime($this->getDomain(), $this->getMonth());
    }

    public function getSlowestResponseTime()
    {
        return $this->logsService->getSlowestResponseTime($this->getDomain(), $this->getMonth());
    }

    public function getUsedQueryParams()
    {
        return $this->logsService->getUsedQueryParams($this->getDomain(), $this->getMonth());
    }
}