<?php

declare(strict_types=1);

namespace Zero1\MagentoDev\Command\Module;

use Minicli\Command\CommandController;
use Zero1\MagentoDev\CommandSummaryInterface;
use Minicli\App;
use Minicli\Input;

class InstallController extends CommandController implements CommandSummaryInterface
{
    protected const LOCATION_KEY = 'location';
    protected const LOCATION_APP = 'app';
    protected const LOCATION_EXT = 'ext';

    /**
     * Return a summary of the command
     * 
     * @param App $app
     *
     * @return void
     */
    public function getSummary(App $app): void
    {
        $app->getPrinter()->out('module install source="clone url" [location=app|ext (default: ext)] - install a Magento module from source control');
    }

    public function handle(): void
    {
        
        $source = $this->determineParam('source');
        $this->getPrinter()->out('source: ', 'bold');
        $this->getPrinter()->out(json_encode($source));

        $location = $this->determineParam('location', self::LOCATION_EXT);
        $this->getPrinter()->out('location: ', 'bold');
        $this->getPrinter()->out(json_encode($location));
        $this->getPrinter()->newline();


        $tmpPath = $this->cloneToTemp($source);

        $packageName = $this->getPackageName($tmpPath);

        $this->moveToLocalDirectory($tmpPath, $packageName, $location);

        if($location == self::LOCATION_EXT){
            $this->ignoreExtensionsDirectory();
            $this->composerInstall($packageName);

            $this->checkForAppInstall($packageName);
        }

        echo 'all done?'.PHP_EOL;
    }

    protected function cloneToTemp($source)
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'magentodev-');
        unlink($tempFile);
        mkdir($tempFile);
        exec('git clone '.$source.' '.$tempFile.'/', $output, $exitCode);
        if($exitCode > 0){
            print_r($output);
            throw new \Exception('Command failed', $exitCode);
        }
        return $tempFile;
    }

    protected function getPackageName($directory)
    {
        $composerJsonPath = $directory.'/composer.json';
        if(!is_file($composerJsonPath)){
            throw new \Exception('Unable to find composer.json: '.$composerJsonPath);
        }
        $composerJson = json_decode(file_get_contents($composerJsonPath), true);
        if(!isset($composerJson['name'])){
            throw new \Exception('Name not found in '.$composerJsonPath);
        }
        return $composerJson['name'];
    }

    protected function moveToLocalDirectory($sourceDirectory, $packageName, $location)
    {
        switch($location){
            case self::LOCATION_APP:
                $targetDirectory = 'app/code';
            break;
            case self::LOCATION_EXT:
                $targetDirectory = 'extensions';
            break;
        }
        $targetDirectory .= '/'.$packageName;
        
        if(!is_dir(dirname($targetDirectory))){
            mkdir(dirname($targetDirectory), 0777, true);
        }
        if(is_dir($targetDirectory)){
            throw new \Exception($targetDirectory.' already exists, please remove and try again');
        }
        exec('mv '.$sourceDirectory.' '.$targetDirectory, $output, $exitCode);
        if($exitCode > 0){
            print_r($output);
            throw new \Exception('Command failed', $exitCode);
        }
    }

    protected function ignoreExtensionsDirectory()
    {
        file_put_contents('extensions/.gitignore', '*'.PHP_EOL);
    }

    protected function composerInstall($packageName)
    {
        $composerJsonPath = 'composer.json';
        if(!is_file($composerJsonPath)){
            throw new \Exception('Unable to find '.$composerJsonPath);
        }
        $composerJson = json_decode(file_get_contents($composerJsonPath), true);
        if(!isset($composerJson['repositories'])){
            $composerJson['repositories'] = [];
        }

        $localRepoConfigured = false;
        foreach($composerJson['repositories'] as $key => $repositoryConfiguration){
            if(isset($repositoryConfiguration['url']) && $repositoryConfiguration['url'] == 'extensions/*/*/'){
                $localRepoConfigured = true;
                break;
            }
        }

        if(!$localRepoConfigured){
            $composerJson['repositories']['local-extension'] = [
                'type' => 'path',
                'url' => 'extensions/*/*/',
            ];
            file_put_contents($composerJsonPath, json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        exec('composer require '.$packageName, $output, $exitCode);
        if($exitCode > 0){
            print_r($output);
            throw new \Exception('There was an error installing the extension', $exitCode);
        }
    }

    protected function checkForAppInstall()
    {

    }

    protected function configure()
    {
        return [
            self::LOCATION_KEY => $this->determineParam(self::LOCATION_KEY, self::LOCATION_EXT, 'Where would you like to create the module?', [
                self::LOCATION_EXT => 'in the ./extensions directory',
                self::LOCATION_APP => 'in the ./app/code directory',
            ]),
        ];
    }

    protected function determineParam(
        $key,
        $defaultValue = null,
        $userPrompt = '',
        $values = []
    ){
        $value = $defaultValue;
        if($this->hasParam($key)){
            $value = $this->getParam($key);
        }else{
            $this->getPrinter()->out($userPrompt, 'bold');
            $this->getPrinter()->newline();
            if(!empty($values)){
                $this->getPrinter()->out('Value options are:');
                $this->getPrinter()->newline();
                foreach($values as $valueOption => $valueDescription){
                    $this->getPrinter()->out(sprintf('%s - %s', $valueOption, $valueDescription));
                    $this->getPrinter()->newline();
                }
                $input = new Input('>');
                $value = $input->read();
            }
        }
        return $value;
    }
}
