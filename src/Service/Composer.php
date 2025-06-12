<?php

namespace Zero1\MagentoDev\Service;

use mikehaertl\shellcommand\Command as ShellCommand;

class Composer
{
    protected $modules;
    
    public function addRepository($key, $configuration)
    {
        $composerJson = $this->loadComposerJson();
        if(!isset($composerJson['repositories'])){
            $composerJson['repositories'] = [];
        }

        $composerJson['repositories'][$key] = $configuration;

        $this->writeComposerJson($composerJson);
    }

    public function removeRepository($key)
    {
        $composerJson = $this->loadComposerJson();
        if(!isset($composerJson['repositories']) || !isset($composerJson['repositories'][$key])){
            return;
        }
        unset($composerJson['repositories'][$key]);

        $this->writeComposerJson($composerJson);
    }

    public function loadComposerJson()
    {
        return json_decode(file_get_contents('composer.json'), true);
    }

    public function writeComposerJson($composerJson)
    {
        file_put_contents('composer.json', json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function require($package, $version = '')
    {
        $command = new ShellCommand('composer require '.$package.($version? ':'.$version : ''));
        if ($command->execute()) {
            return $command->getOutput();
        } else {
            throw new \RuntimeException(
                'Composer require failed: ' . $command->getError(),
                $command->getExitCode()
            );
        }
    }
}