<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace MDOQ\SitePerformanceMonitoring\Model;

/**
 * SiteValidator validator for Site model
 */
class SiteValidator extends \Magento\Framework\Validator\DataObject
{
    public function __construct(
        array $rules = []
    ) {
        foreach($rules as $ruleId => $ruleSpec) {
            $this->addRule($ruleSpec['validator'], $ruleSpec['field']);
        }
    }
}
