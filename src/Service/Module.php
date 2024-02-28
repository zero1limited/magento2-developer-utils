<?php

namespace Zero1\MagentoDev\Service;

use League\Flysystem\Filesystem;
use Zero1\MagentoDev\Factory\FileSystemFactory;
use Zero1\MagentoDev\Service\Module\UnableToLocateModuleException;

class Module
{
    public const GLOB_PATTERNS = [
        'app/code/*/*/registration.php',
        'extensions/*/*/registration.php'
    ];

    protected $registrationPaths = [];

    protected $modules = [];
    
    /** @var Filesystem */
    protected $filesystem;

    public function __construct(
        FileSystemFactory $fileSystemFactory
    ) {
        $this->filesystem = $fileSystemFactory->build();
    }

    public function locate($moduleName)
    {
        if(!isset($this->modules[$moduleName])){
            $module = null;
    
            foreach(self::GLOB_PATTERNS as $globPattern){
                $files = glob($globPattern, GLOB_NOSORT);
                if($files){
                    foreach($files as $file){
                        $fileContent = $this->filesystem->read($file);
                        if(strpos($fileContent, '\''.$moduleName.'\'') !== false){
                            $module = $this->loadModule($moduleName, dirname($file));
                            break 2;
                        }
                    }
                }
            }
            if(!$module){
                throw new UnableToLocateModuleException('Unable to locate the module: '.$moduleName);
            }
            $this->modules[$moduleName] = $module;
            
        }
        return $this->modules[$moduleName];
    }

    /**
     * @param string $moduleName
     * @param string $directory
     * @return \Zero1\MagentoDev\Model\module
     */
    protected function loadModule($moduleName, $directory)
    {
        return new \Zero1\MagentoDev\Model\Module(
            $moduleName, 
            $directory,
            $this->filesystem
        );
    }
}