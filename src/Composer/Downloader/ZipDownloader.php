<?php

namespace Zero1\MagentoDev\Composer\Downloader;

use Composer\Downloader\ZipDownloader as ComposerZipDownloader;
use Composer\Config;
use Composer\Cache;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Exception\IrrecoverableDownloadException;
use Composer\Package\Comparer\Comparer;
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

class ZipDownloader extends ComposerZipDownloader
{
    protected $comparer;

    /**
     * @param \Composer\Package\Comparer\Comparer|null $comparer
     * @return self
     */
    public function setComparer($comparer)
    {
        $this->comparer = $comparer;
        return $this;
    }

    /**
     * @return null|\Composer\Package\Comparer\Comparer
     */
    public function getComparer()
    {
        return $this->comparer;
    }

    /**
     * @inheritDoc
     * @throws \RuntimeException
     */
    public function getLocalChanges(
        PackageInterface $package, 
        $targetDir
    ): ?string {
        $prevIO = $this->io;

        $this->io = new NullIO;
        $this->io->loadConfiguration($this->config);
        $e = null;
        $output = '';

        $targetDir = Filesystem::trimTrailingSlash($targetDir);
        try {
            if (is_dir($targetDir.'_compare')) {
                $this->filesystem->removeDirectory($targetDir.'_compare');
            }

            $this->download($package, $targetDir.'_compare', null, false);
            $this->httpDownloader->wait();
            $this->install($package, $targetDir.'_compare', false);
            $this->process->wait();

            $comparer = $this->getComparer();
            if(!$comparer){
                $this->setComparer(new Comparer());
                $comparer = $this->getComparer();
            }
            $comparer->setSource($targetDir.'_compare');
            $comparer->setUpdate($targetDir);
            $comparer->doCompare();
            $output = $comparer->getChangedAsString(true);
            $this->filesystem->removeDirectory($targetDir.'_compare');
        } catch (\Exception $e) {
        }

        $this->io = $prevIO;

        if ($e) {
            throw $e;
        }

        return trim($output);
    }
}
