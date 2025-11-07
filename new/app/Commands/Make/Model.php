<?php

namespace App\Commands\Make;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Facades\File;

class Model extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:model';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new model class';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $magentoRoot = '/home/magento/htdocs/';
        $extensionDir = 'extensions/mdoq/site-performance-testing/';

        $withResource = true;
        $withValidation = true;
        $withInterface = true;
        $force = true;
        
        $vendor = 'MDOQ';
        $module = 'SitePerformanceMonitoring';
        $modelClassName = 'Site';
        $modelClassPrefix = '';

        $modelFilePath = $magentoRoot.$extensionDir.'Model'.str_replace('\\', '/', $modelClassPrefix).'/'.$modelClassName.'.php';

        $moduleNamespace = $vendor.'\\'.$module;
        $classNamespace = $moduleNamespace.'\\Model'.$modelClassPrefix;
        $className = $modelClassName;
        $interfaceName = $modelClassName.'Interface';
        $validatorName = $modelClassName.'Validator';
        $classInterface = $moduleNamespace.'\Api\Data\\'.$interfaceName;
        $resourceModel = $classNamespace.'\ResourceModel\\'.$className;
        $cacheTag = strtolower(implode('_', [$vendor, $module, $modelClassName]));
        $classValidator = $classNamespace.'\\'.$validatorName;
        $classFields = [
            [
                'id' => 'name',
                'type' => 'string',
                'method_suffix' => 'Name',
                'accessor_key' => $withInterface ? '\'name\'' : 'self::NAME',
            ],
            [
                'id' => 'created_at',
                'type' => 'string',
                'method_suffix' => 'CreatedAt',
                'accessor_key' => $withInterface ? '\'created_at\'' : 'self::CREATED_AT',
            ],
            [
                'id' => 'updated_at',
                'type' => 'string',
                'method_suffix' => 'UpdatedAt',
                'accessor_key' => $withInterface ? '\'updated_at\'' : 'self::UPDATED_AT',
            ],
        ];

        if ($force || !File::exists($modelFilePath)) {
            File::put($modelFilePath, view('make.model', [
                'doc_header' => '/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */',
                'namespace' => $classNamespace,
                'class_name' => $className,
                'class_interface' => $withInterface ? $classInterface : null,
                'class_fields' => $classFields,
                'interface_name' => $withInterface ? $interfaceName : null,
                'class_validator' => $withValidation ? $classValidator : null,
                'resource_model' => $withResource ? $resourceModel : null,
                'cache_tag' => $cacheTag,
            ])->render());
        }

        $this->info("Model created: {$modelFilePath}");

        if($withValidation){
            
            $validatorFilePath = $magentoRoot.$extensionDir.'Model'.str_replace('\\', '/', $modelClassPrefix).'/'.$validatorName.'.php';
            if ($force || !File::exists($validatorFilePath)) {
                File::put($validatorFilePath, view('make.model_validator', [
                    'doc_header' => '/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */',
                    'namespace' => $classNamespace,
                    'class_name' => $className,
                    'model_class_path' => $classNamespace.'\\'.$className,
                    'class_interface' => $withInterface ? $classInterface : null,
                    'class_fields' => $classFields,
                    'interface_name' => $withInterface ? $interfaceName : null,
                    'validator_name' => $validatorName,
                ])->render());
            }
        }
        
        return 0;
    }
}
