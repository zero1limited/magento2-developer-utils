<?php
namespace MDOQ\SitePerformanceMonitoring\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class Configuration extends AbstractHelper
{
    const XML_PATH_ALLOWED_DOMAINS = 'performance_monitoring/general/allowed_domains';

    /**
     * Get allowed domains for logging
     *
     * @return array
     */
    public function getAllowedDomains(): array
    {
        $domains = $this->scopeConfig->getValue(self::XML_PATH_ALLOWED_DOMAINS);
        return explode(',', $domains ?? '');
    }
}