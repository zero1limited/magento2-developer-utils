<?php
namespace MDOQ\SitePerformanceMonitoring\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Class Site Resource Model
 */
class Site extends AbstractDb
{
    public const TABLE_NAME = 'mdoq_siteperformancemonitoring_site';

    /**
     * Init
     */
    protected function _construct()
    {
        $this->_init(self::TABLE_NAME, 'id');
    }
}
