<?php
namespace MDOQ\SitePerformanceMonitoring\Block\Adminhtml\Site\Edit;

use Magento\Backend\Block\Widget\Context;
use MDOQ\SitePerformanceMonitoring\Api\SiteRepositoryInterface as ModelRepository;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Class GenericButton
 */
class GenericButton
{
    public function __construct(
        protected Context $context,
        protected ModelRepository $modelRepository
    ) {
    }

    /**
     * Return Model ID
     *
     * @return int|null
     */
    public function getModelId()
    {
        try {
            return $this->modelRepository->getById(
                $this->context->getRequest()->getParam('id')
            )->getId();
        } catch (NoSuchEntityException $e) {
        }
        return null;
    }

    /**
     * Generate url by route and parameters
     *
     * @param   string $route
     * @param   array $params
     * @return  string
     */
    public function getUrl($route = '', $params = [])
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
