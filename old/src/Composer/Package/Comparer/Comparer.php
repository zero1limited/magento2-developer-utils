<?php

namespace Zero1\MagentoDev\Composer\Package\Comparer;

use Composer\Downloader\ZipDownloader as ComposerZipDownloader;
use Composer\Config;
use Composer\Cache;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Exception\IrrecoverableDownloadException;
use Composer\Package\Comparer\Comparer as ComposerComparer;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PostFileDownloadEvent;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Util\Filesystem;
use Composer\Util\Silencer;
use Composer\Util\HttpDownloader;
use Composer\Util\Url as UrlUtil;
use Composer\Util\ProcessExecutor;
use React\Promise\PromiseInterface;
use mikehaertl\shellcommand\Command;

class Comparer extends ComposerComparer
{
    /** @var string Source directory */
    protected $source;

    /** @var string Target directory */
    protected $update;

    /** @var array{changed?: string[], removed?: string[], added?: string[]} */
    protected $changed; 
    
    /**
     * @var string
     */
    protected $patchRaw = '';

    /**
     * @var string
     */
    protected $patchColoured = '';

    /**
     * @var array<string>
     */
    protected $fileExclusions = [];

    /**
     * @param string $source
     *
     * @return void
     */
    public function setSource($source): void
    {
        $this->source = $source;
        parent::setSource($source);
    }

    /**
     * @param string $update
     *
     * @return void
     */
    public function setUpdate($update): void
    {
        $this->update = $update;
        parent::setUpdate($update);
    }

    public function getPatch($coloured = false)
    {
        if($coloured){
            return $this->patchColoured;
        }
        return $this->patchRaw;
    }

    public function setFileExclusions($exclusions = [])
    {
        $this->fileExclusions = $exclusions;
        return $this;
    }

    /**
     * @return void
     */
    public function doCompare(): void
    {
        parent::doCompare();

        $originalDirectory = getcwd();
        chdir($this->update);

        $exclusions = '';
        foreach($this->fileExclusions as $exclude){
            // might need to change this in future as we might want to include a file in one
            // directory with the same name as one we want to exclude in another dir
            // couldnt get paths working atm though
            $exclusions .= '--exclude='.basename($exclude).' ';
        }

        $rawCommand = 'diff -urN '.$this->source.' ./ '.$exclusions;
        $colouredCommand = $rawCommand.' --color=always';

        $diffCommand = new Command($rawCommand);
        $diffCommand->execute();
        $this->patchRaw = $diffCommand->getOutput();

        $diffCommand = new Command($colouredCommand);
        $diffCommand->execute();
        $this->patchColoured = $diffCommand->getOutput();

        // if(!empty($this->fileExclusions)){
        //     die('aaaaaa'.PHP_EOL);
        // }
        

        chdir($originalDirectory);
    }
}