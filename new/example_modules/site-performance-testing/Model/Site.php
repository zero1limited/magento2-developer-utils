<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace MDOQ\SitePerformanceMonitoring\Model;

use MDOQ\SitePerformanceMonitoring\Api\Data\SiteInterface as SiteInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use MDOQ\SitePerformanceMonitoring\Model\ResourceModel\Site as ResourceModel;
use MDOQ\SitePerformanceMonitoring\Model\SiteValidator as Validator;
use Magento\Framework\Validation\ValidationException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb as AbstractDbCollection;

/**
 * Site model
 */
class Site extends AbstractModel implements SiteInterface, IdentityInterface
{
    public const CACHE_TAG = 'mdoq_siteperformancemonitoring_site';

    /**
     * @var string
     */
    protected $_cacheTag = self::CACHE_TAG;

    /**
     * Prefix of model events names
     *
     * @var string
     */
    protected $_eventPrefix = 'mdoq_siteperformancemonitoring_site';

    public function __construct(
        Validator $validator,
        Context $context,
        Registry $registry,
        ?AbstractResource $resource = null,
        ?AbstractDbCollection $resourceCollection = null,
        array $data = []
    ){
        $this->_validatorBeforeSave = $validator;
        parent::__construct(
            $context,
            $registry,
            $resource,
            $resourceCollection,
            $data
        );
    }
    
    /**
     * Construct.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ResourceModel::class);
    }

    /**
     * Get identities
     *
     * @return array
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * Set name
     *
     * @param string $name
     * @return self
     */
    public function setName(string $name): self
    {
        return $this->setData('name', $name);
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->getData('name');
    }

    /**
     * Set created_at
     *
     * @param string $created_at
     * @return self
     */
    public function setCreatedAt(string $created_at): self
    {
        return $this->setData('created_at', $created_at);
    }

    /**
     * Get created_at
     *
     * @return string
     */
    public function getCreatedAt()
    {
        return $this->getData('created_at');
    }

    /**
     * Set updated_at
     *
     * @param string $updated_at
     * @return self
     */
    public function setUpdatedAt(string $updated_at): self
    {
        return $this->setData('updated_at', $updated_at);
    }

    /**
     * Get updated_at
     *
     * @return string
     */
    public function getUpdatedAt()
    {
        return $this->getData('updated_at');
    }
        
    /**
     * @return AbstractModel
     * @throws \Magento\Framework\Validation\ValidationException
     */
    public function beforeSave()
    {
        if ($this->hasDataChanges()) {
            $this->setUpdateTime(null);
        }

        // $validationResult = $this->validator->validate($this);
        // if (!$validationResult->isValid()) {
        //     throw new ValidationException(__('Validation Failed'), null, 0, $validationResult);
        // }

        return parent::beforeSave();
    }
}
