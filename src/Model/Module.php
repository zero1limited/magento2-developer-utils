<?php

namespace Zero1\MagentoDev\Model;

use JsonPath\JsonObject;
use League\Flysystem\Filesystem;

class Module
{
    /** @var string */
    protected $moduleName;

    /** @var string */
    protected $baseDirectory;

    /** @var \League\Flysystem\Filesystem */
    protected $fileSystem;

    /** @var string */
    protected $namespace;

    /** @var string */
    protected $namespacedDirectory;

    /**
     * @param string $moduleName
     * @param string $directory
     * @param \League\Flysystem\Filesystem $fileSystem
     */
    public function __construct(
        $moduleName,
        $directory,
        $fileSystem
    ){
        $this->moduleName = $moduleName;
        $this->baseDirectory = $directory;
        $this->fileSystem = $fileSystem;

        if($this->fileSystem->fileExists($this->baseDirectory.'/composer.json')){
            $composerJson = new JsonObject(
                $this->fileSystem->read($this->baseDirectory.'/composer.json'),
                true
            );
            $namespace = $composerJson->get('$.autoload.psr-4');
            if(!$namespace || empty($namespace) || count($namespace) > 1){
                throw new \Exception('unable to determine namespace: '.json_encode($namespace));
            }
            $this->namespace = array_key_first($namespace);
            $this->namespacedDirectory = rtrim($this->baseDirectory.'/'.$namespace[array_key_first($namespace)], '/');
        }else{
            throw new \Exception('no composer.json, dont know namespace');
        }
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->moduleName;
    }

    /**
     * @return string
     */
    public function getBaseDirectory()
    {
        return $this->baseDirectory;
    }

    /**
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @return string
     */
    public function getNamespacedDirectory()
    {
        return $this->namespacedDirectory;
    }
}