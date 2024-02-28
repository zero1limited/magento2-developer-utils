<?php

namespace Zero1\MagentoDev\Factory;

class FileSystemFactory
{
    /** @var \League\Flysystem\Filesystem */
    protected static $filesystem;

    /**
     * @return \League\Flysystem\Filesystem
     */
    public function build()
    {
        if(!self::$filesystem){
            $adapter = new \League\Flysystem\Local\LocalFilesystemAdapter(
                // Determine root directory
                getcwd()
            );
            
            // The FilesystemOperator
            self::$filesystem = new \League\Flysystem\Filesystem($adapter);
        }
        return self::$filesystem;
    }
}