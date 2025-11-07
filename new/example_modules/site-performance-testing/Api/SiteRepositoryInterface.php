<?php
namespace MDOQ\SitePerformanceMonitoring\Api;

use MDOQ\SitePerformanceMonitoring\Api\Data\SiteInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

/**
 * Interface SiteRepositoryInterface
 *
 * @api
 */
interface SiteRepositoryInterface
{
    public function getNew(): SiteInterface;
    
    /**
     * Create or update a Site.
     *
     * @param SiteInterface $page
     * @return SiteInterface
     */
    public function save(SiteInterface $page);

    /**
     * Get a Site by Id
     *
     * @param int $id
     * @return SiteInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException If Site with the specified ID does not exist.
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getById($id);

    /**
     * Retrieve Sites which match a specified criteria.
     *
     * @param SearchCriteriaInterface $criteria
     */
    public function getList(SearchCriteriaInterface $criteria);

    /**
     * Delete a Site
     *
     * @param SiteInterface $page
     * @return SiteInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException If Site with the specified ID does not exist.
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(SiteInterface $page);

    /**
     * Delete a Site by Id
     *
     * @param int $id
     * @return SiteInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException If customer with the specified ID does not exist.
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteById($id);
}
