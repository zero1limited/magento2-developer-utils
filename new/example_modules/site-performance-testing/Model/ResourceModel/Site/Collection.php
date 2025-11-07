<?php
namespace MDOQ\SitePerformanceMonitoring\Model\ResourceModel\Site;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Class Collection for Site
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'mdoq_sitepeformancemonitoring_site_collection';

    /**
     * @var string
     */
    protected $_eventObject = 'mdoq_sitepeformancemonitoring_site_collection';

    protected function _construct()
    {
        $this->_init(
            \MDOQ\SitePerformanceMonitoring\Model\Site::class,
            \MDOQ\SitePerformanceMonitoring\Model\ResourceModel\Site::class
        );
    }
}
