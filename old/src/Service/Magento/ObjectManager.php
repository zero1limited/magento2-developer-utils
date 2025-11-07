<?php

namespace Zero1\MagentoDev\Service\Magento;

use League\Flysystem\Filesystem;
use Zero1\MagentoDev\Factory\FileSystemFactory;
use Zero1\MagentoDev\Service\Module\UnableToLocateModuleException;

class ObjectManager
{
    /** @var \Magento\Framework\ObjectManagerInterface */
    protected $objectManager = null;
    
    /** @var Filesystem */
    protected $filesystem;

    public function __construct(
        FileSystemFactory $fileSystemFactory
    ) {
        $this->filesystem = $fileSystemFactory->build();
    }

    /**
     * @return \Magento\Framework\ObjectManagerInterface
     */
    public function getObjectManager()
    {
        if(!$this->objectManager){
            require './app/bootstrap.php';
            $bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
            $this->objectManager = $bootstrap->getObjectManager();
        }
        return $this->objectManager;
    }
}