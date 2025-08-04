<?php

namespace Zero1\MagentoDev\Command\Upgrade;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\ChoiceQuestion;
use \Composer\Console\Application as ComposerApplication;
use \Composer\Downloader\ChangeReportInterface;
use \Composer\Downloader\DvcsDownloaderInterface;
use \Composer\Downloader\VcsCapableDownloaderInterface;
use \Composer\Package\Dumper\ArrayDumper;
use \Composer\Package\Version\VersionGuesser;
use \Composer\Package\Version\VersionParser;
use \Composer\Plugin\CommandEvent;
use \Composer\Plugin\PluginEvents;
use \Composer\Script\ScriptEvents;
use \Composer\Util\ProcessExecutor;
use Zero1\MagentoDev\Composer\Downloader\ZipDownloader;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Filesystem\Filesystem;
use mikehaertl\shellcommand\Command as ShellCommand;

class Magento extends Command
{
    protected static $defaultName = 'upgrade:magento';

    /** @var Filesystem */
    protected $filesystem;

    public function __construct(
    ) {
        parent::__construct();
        $this->filesystem = new Filesystem();
    }

    protected function configure(): void
    {
        $this->setDescription('Upgrade magento')
            ->setHelp('This command allows you to upgrade magento');

        $this->addOption('target-version', null, InputOption::VALUE_OPTIONAL, 'version to upgrade to', null);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return integer
     * 
     * @see vendor/composer/composer/src/Composer/Command/StatusCommand.php
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if(!$this->hasStepBeenCompleted('gather-details')){
            $targetVersion = $input->getOption('target-version');
            if (!$targetVersion) {
                $output->writeln('<error>You must specify a target version using --target-version</error>');
                return 1;
            }
            $output->writeln('<info>Target version: </info>'.$targetVersion);

            $cmd = new ShellCommand('composer show --no-plugins -a magento/product-community-edition --format=json', ['timeout' => 60]);
            if(!$cmd->execute()){
                $output->writeln('<error>Unable to get magento versions</error>');
                $output->writeln('Output: '.$cmd->getOutput());
                $output->writeln('Error: '.$cmd->getError());
                return $cmd->getExitCode();
            }
            $info = json_decode($cmd->getOutput(), true);
            if(!isset($info['versions']) || !is_array($info['versions'])){
                $output->writeln('<error>Unable to get magento versions (not found)</error>');
                $output->writeln('Output: '.$cmd->getOutput());
                return 1;
            }

            $versions = array_filter($info['versions'], function($version) use ($targetVersion) {
                return strpos($version, $targetVersion) === 0;
            });
            if(empty($versions)){
                $output->writeln('<error>Target version not found in available versions</error>');
                $output->writeln('Available versions: '.implode(', ', $info['versions']));
                return 1;
            }

            if(count($versions) > 1){
                $output->writeln('<comment>Multiple versions found for target version, using the latest one</comment>');
                $v = null;
                foreach($versions as $version){
                    if(!$v || version_compare($version, $v, '>')){
                        $v = $version;
                    }
                }
                $version = $v;
            }else{
                $version = $targetVersion;
            }
            $output->writeln('<info>Using version: </info>'.$version);

            $currentComposerInfo = json_decode(file_get_contents('composer.json'), true);
            if(!isset($currentComposerInfo['require']['magento/product-community-edition'])){
                $output->writeln('<error>magento/product-community-edition not found in composer.json</error>');
                return 1;
            }
            $currentVersion = $currentComposerInfo['require']['magento/product-community-edition'];
            $output->writeln('<info>Current version: </info>'.$currentVersion);
            if(!in_array($currentVersion, $info['versions'])){
                $output->writeln('<error>Current version not found in available versions</error>');
                $output->writeln('Available versions: '.implode(', ', $info['versions']));
                return 1;
            }

            if(version_compare($currentVersion, $version, '>=')){
                $output->writeln('<error>Target version is not greater than current version</error>');
                return 1;
            }

            $this->updateProgress('current', $currentVersion);
            $this->updateProgress('target', $version);
            $this->updateProgress('gather-details');
        }

        $currentVersion = $this->getProgress('current');
        $version = $this->getProgress('target');

        if(!$this->hasStepBeenCompleted('auth.json')){
            $cmd = new ShellCommand('composer config home', ['timeout' => 60]);
            $cmd->execute();
            $composerHome = trim($cmd->getOutput());
            if(!$composerHome){
                $output->writeln('<error>Unable to get composer home directory</error>');
                return 1;
            }

            if(!is_file('auth.json')){
                $output->writeln('<error>auth.json not found, please create it with your credentials</error>');
                return 1;
            }

            if(!is_dir($composerHome)){
                $this->filesystem->mkdir($composerHome, 0766);
                $output->writeln('<info>Created composer home directory: </info>'.$composerHome);
            }
            $this->filesystem->copy('auth.json', $composerHome . '/auth.json', true);
            $this->updateProgress('auth.json');
        }else{
            $output->writeln('<info>auth.json already configured, skipping...</info>');
        }

        if(!$this->hasStepBeenCompleted('clean-installs')){
            $output->write('<info>Downloading clean installs...</info>');
            $cmds = [
                'rm -rf upgrade_work_dir/current || true',
                'rm -rf upgrade_work_dir/target || true',
                'rm -rf upgrade_work_dir/working || true',
                'mkdir -p upgrade_work_dir/current upgrade_work_dir/target upgrade_work_dir/working',
                'cd upgrade_work_dir',
                'composer create-project --repository-url=https://repo.magento.com/ --no-install --no-interaction --prefer-dist magento/project-community-edition:'.$currentVersion.' current/',
                'composer create-project --repository-url=https://repo.magento.com/ --no-install --no-interaction --prefer-dist magento/project-community-edition:'.$version.' target/',
                'cp -r target/* working/',
                'cp ../auth.json working/'
            ];
            $cmd = new ShellCommand(implode(' && ', $cmds));
            if(!$cmd->execute()){
                $output->writeln('');
                $output->writeln('<error>Unable to download clean installs</error>');
                $output->writeln('Output: '.$cmd->getOutput());
                $output->writeln('Error: '.$cmd->getError());
                $output->writeln('Command was: '.implode(' \\'.PHP_EOL.'  && ', $cmds));
                return $cmd->getExitCode();
            }
            $output->writeln('<info>OK</info>');
            $this->updateProgress('clean-installs');
        }else{
            $output->writeln('<info>Clean installs already downloaded, skipping...</info>');
        }

        if(!$this->hasStepBeenCompleted('composer-backup')){
            $output->write('<info>Backing up composer.json and composer.lock ...</info>');
            $this->filesystem->copy('composer.json', 'upgrade_work_dir/composer.json.bak', true);
            $this->filesystem->copy('composer.lock', 'upgrade_work_dir/composer.lock.bak', true);
            $this->updateProgress('composer-backup');
            $output->writeln('<info>OK</info>');
        }else{
            $output->writeln('<info>Composer files already backed up, skipping...</info>');
        }

        $output->writeln('<info>Comparing current => original files...</info>');
        $currentFlatterned = $this->getFlatterned('upgrade_work_dir/composer.json.bak');
        $targetFlatterned = $this->getFlatterned('upgrade_work_dir/target/composer.json');
        $originalFlatterned = $this->getFlatterned('upgrade_work_dir/current/composer.json');
        // removed?
        $removed = array_diff_key($originalFlatterned, $currentFlatterned);
        $output->writeln('<comment>Removed: '.count($removed).'</comment>');
        print_r($removed);
            
        // added?
        $added = array_diff_key($currentFlatterned, $originalFlatterned);
        $output->writeln('<comment>Added: '.count($added).'</comment>');
        print_r($added);

        if(!$this->hasStepBeenCompleted('simple-removes')){
            $output->write('<info>Apply simple removes...</info>');
            
            $workingComposer = json_decode(file_get_contents('upgrade_work_dir/working/composer.json'), true);
            $cmds = [
                'cd upgrade_work_dir/working'
            ];
            foreach($removed as $key => $value){
                if(strpos($key, 'require.') === 0){
                    $cmds[] = 'composer remove --no-install --no-update --no-plugins ' . str_replace('require.', '', $key);
                }elseif(strpos($key, 'require-dev.') === 0){
                    $cmds[] = 'composer remove --dev --no-install --no-update --no-plugins ' . str_replace('require-dev.', '', $key);
                }else{
                    $this->unset($workingComposer, $key);
                }
            }
            $this->writeComposerFile('upgrade_work_dir/working/composer.json', $workingComposer);
            
            $cmd = new ShellCommand(implode(' && ', $cmds));
            if(!$cmd->execute()){
                $output->writeln('');
                $output->writeln('<error>Unable to download clean installs</error>');
                $output->writeln('Output: '.$cmd->getOutput());
                $output->writeln('Error: '.$cmd->getError());
                $output->writeln('Command was: '.implode(' \\'.PHP_EOL.'  && ', $cmds));
                return $cmd->getExitCode();
            }
            $output->writeln('<info>OK</info>');
            $this->updateProgress('simple-removes');
        }else{
            $output->writeln('<info>Simple removes already applied, skipping...</info>');
        }

        if(!$this->hasStepBeenCompleted('simple-adds')){
            $output->write('<info>Apply simple adds...</info>');

            foreach($added as $key => $value){
                if(strpos($key, 'require.') === false && strpos($key, 'require-dev.') === false){
                    $this->set($workingComposer, $key, $value);
                }
            }
            $this->writeComposerFile('upgrade_work_dir/working/composer.json', $workingComposer);
            $output->writeln('<info>OK</info>');
            $this->updateProgress('simple-adds');
        }else{
            $output->writeln('<info>Simple adds already applied, skipping...</info>');
        }
        
        $output->writeln('<info>Time to upgrade PHP version</info>');
        $helper = new \Symfony\Component\Console\Helper\QuestionHelper();
        $question = new ConfirmationQuestion('<question>Have you upgraded php to an appropriate version? (y/n)</question> ', false);
        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('Please upgrade PHP to an appropriate version and run this command again.');
            return 1;
        }

        if(!$this->hasStepBeenCompleted('composer-install')){
            $output->write('<info>Initial install of new version...</info>');
            $this->filesystem->copy('upgrade_work_dir/working/composer.json', 'composer.json', true);
            if(is_file('composer.lock')){
                $this->filesystem->remove('composer.lock');
            }

            $cmd = new ShellCommand([
                'command' => 'composer install',
                'timeout' => 60,
            ]);
            $cmd->setStdIn('y'); // say yes to any questions

            if(!$cmd->execute()){
                $output->writeln('');
                $output->writeln('<error>Unable to install composer dependencies</error>');
                $output->writeln('Output: '.$cmd->getOutput());
                $output->writeln('Error: '.$cmd->getError());
                $output->writeln('Command was: '.$cmd->getCommand());
                die('!!!');
            }
            $output->writeln('<info>OK</info>');
            $this->updateProgress('composer-install');
        }else{
            $output->writeln('<info>Composer dependencies already installed, skipping...</info>');
        }

        if(!$this->hasStepBeenCompleted('complex-adds')){
            $output->writeln('<info>Apply complex adds...</info>');
            $requires = [];

            foreach($added as $key => $value){
                if(strpos($key, 'require.') === 0 || strpos($key, 'require-dev.') === 0){

                    if(strpos($key, 'require-dev.') !== false){
                        $isDev = true;
                        $module = str_replace('require-dev.', '', $key);
                    }else{
                        $isDev = false;
                        $module = str_replace('require.', '', $key);
                    }
                    
                    $output->write('  Trying '.$module.':'.$value.'...');
                    $cmd = new ShellCommand([
                        'command' => 'composer require --no-plugins '.($isDev? '--dev ' : '').'-- ' . $module . ':'.$value,
                        'timeout' => 60,
                    ]);
                    $cmd->setStdIn('y'.PHP_EOL); // say yes to any questions

                    if(!$cmd->execute()){
                        // try installing versionless
                        $output->writeln('<error>X</error>');
                        $output->write('  Trying '.$module.'...');
                        $cmd = new ShellCommand([
                            'command' => 'composer require --no-plugins '.($isDev? '--dev ' : '').'-- ' . $module,
                            'timeout' => 60,
                        ]);
                        $cmd->setStdIn('y'); // say yes to any questions

                        if(!$cmd->execute()){
                            $requires[$module] = [
                                'output' => $cmd->getOutput(),
                                'error' => $cmd->getError(),
                                'command' => $cmd->getCommand(),
                            ];
                            $output->writeln('<error>X</error>');
                        }else{
                            $output->writeln('<info>OK</info>');
                            $requires[$module] = 2;
                        }
                    }else{
                        $output->writeln('<info>OK</info>');
                        $requires[$module] = 1;
                    }
                }
            }
            $this->updateProgress('install-log', $requires);
            $output->writeln('<info>OK</info>');
            $this->updateProgress('complex-adds');
        }else{
            $output->writeln('<info>complex adds already applied, skipping...</info>');
        }

        $output->writeln('Upgrade complete (as complete as this script can get it)');
        $installLog = $this->getProgress('install-log');
        
        $failedToInstall = [];
        foreach($installLog as $key => $value){
            if(is_array($value)){
                $failedToInstall[] = $key;
            }
        }

        if(empty($failedToInstall)){
            $output->writeln('<info>All modules installed successfully!</info>');
        }else{
            $output->writeln('<error>Failed to install the following modules:</error>');
            foreach($failedToInstall as $module){
                $output->writeln(' - ' . $module);
            }
        }

        $output->writeln('<info>Recommendations:</info>');
        $output->writeln(' - Commit composer.json and composer.lock to source control.');
        $output->writeln(' - Review any failed module installs.');
        $output->writeln(' - Fresh install, reset the codebase and do a composer install');
        $output->writeln(' - Run bin/magento deploy:mode:set production && bin/magento setup:upgrade --keep-generated');

        $output->writeln('<info>Additional Info:</info>');
        $output->writeln(' - upgrade_work_dir contains temporary files and logs. (including original composer.json and composer.lock)');
        $output->writeln(' - upgrade_work_dir/current contains a clean composer.json of the starting version');
        $output->writeln(' - upgrade_work_dir/target contains a clean composer.json of the target version');
        $output->writeln(' - upgrade_work_dir/working contains a dirty composer.json, where all the simple add and removes have been applied');

        return empty($failedToInstall)? 0 : 1;
    }

    protected function getFlatterned(string $file): array
    {
        if(!is_file($file)){
            throw new \Exception('File not found: ' . $file);
        }
        $content = file_get_contents($file);
        if(!$content){
            throw new \Exception('Unable to read file: ' . $file);
        }
        $data = json_decode($content, true);
        if(!$data){
            throw new \Exception('Unable to decode JSON from file: ' . $file);
        }
        return $this->flattern($data);
    }

    protected function flattern($data, $path = '')
    {
        $flatterned = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $flatterned = array_merge($flatterned, $this->flattern($value, $path . $key . '.'));
            } else {
                $flatterned[$path . $key] = $value;
            }
        }
        return $flatterned;
    }

    protected function unset(&$data, $key)
    {
        $keys = explode('.', $key);
        $lastKey = array_pop($keys);
        $current = &$data;
        foreach ($keys as $k) {
            if (!isset($current[$k])) {
                return; // Key does not exist
            }
            $current = &$current[$k];
        }
        unset($current[$lastKey]);
    }

    protected function set(&$data, $key, $value)
    {
        $keys = explode('.', $key);
        $lastKey = array_pop($keys);
        $current = &$data;
        foreach ($keys as $k) {
            if (!isset($current[$k]) || !is_array($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }
        $current[$lastKey] = $value;
    }

    protected function writeComposerFile(string $file, array $data): void
    {
        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($file, $content) === false) {
            throw new \Exception('Unable to write to file: ' . $file);
        }
    }

    protected function updateProgress($key, $value = null)
    {
        $progress = $this->getProgress();
        if($value === null){
            // assume step update
            $progress['steps'][] = $key;
        }else{
            $progress[$key] = $value;
        }

        if(!is_dir('upgrade_work_dir')){
            mkdir('upgrade_work_dir', 0777, true);
        }

        file_put_contents('upgrade_work_dir/progress.json', json_encode($progress, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function getProgress($key = null)
    {
        if(!is_file('upgrade_work_dir/progress.json')){
            $progress = [
                'steps' => [],
            ];
        }else{
            $progress = json_decode(file_get_contents('upgrade_work_dir/progress.json'), true);
        }

        return $key === null ? $progress : $progress[$key] ?? null;
    }

    protected function hasStepBeenCompleted($step): bool
    {
        $progress = $this->getProgress('steps');
        return in_array($step, $progress);
    }
}