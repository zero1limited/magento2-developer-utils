<?php

namespace Zero1\MagentoDev\Service\Magento;

use League\Flysystem\Filesystem;
use Zero1\MagentoDev\Factory\FileSystemFactory;
use Zero1\MagentoDev\Service\Module\UnableToLocateModuleException;
use Zero1\MagentoDev\Service\Magento\ObjectManager;

class Routes
{
    /** @var \Magento\Framework\App\Route\Config\Reader */
    protected $reader;

    protected $routes = [];
    
    /** @var Filesystem */
    protected $filesystem;

    protected ObjectManager $objectManager;

    public function __construct(
        FileSystemFactory $fileSystemFactory,
        ObjectManager $objectManager
    ) {
        $this->filesystem = $fileSystemFactory->build();
        $this->objectManager = $objectManager;
    }

    /**
     * @return \Magento\Framework\App\Route\Config\Reader
     */
    protected function getReader()
    {
        if(!$this->reader){
            $this->reader = $this->objectManager->getObjectManager()->get(\Magento\Framework\App\Route\Config\Reader::class);
        }
        return $this->reader;
    }
    
    /**
     * @param string $area
     * @return array
     */
    public function loadRoutes($area)
    {
        if(!isset($this->routes[$area])){
            $this->routes[$area] = $this->getReader()->read($area);
        }
        return $this->routes[$area];
    }

    /**
     * @param string $area
     * @param string $frontName
     * @return bool
     */
    public function doesRouteWithFrontNameExist($area, $frontName)
    {
        return $this->getRouteByFrontName($area, $frontName) != null;
    }

    /**
     * @param string $area
     * @param string $frontName
     * @return null|array<mixed>
     */
    public function getRouteByFrontName($area, $frontName)
    {
        $routes = $this->loadRoutes($area);
        foreach($routes as $routerId => $routerConfig){
            foreach($routerConfig['routes'] as $routeId => $routeConfig){
                if($routeConfig['frontName'] == $frontName){
                    return $routeConfig;
                }
            }
        }
        return null;
    }
}