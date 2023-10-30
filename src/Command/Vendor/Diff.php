<?php

namespace Zero1\MagentoDev\Command\Vendor;

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

class Diff extends Command
{
    protected static $defaultName = 'vendor:diff';

    public function __construct(
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        // $this->setDescription('Setup a module to be worked on locally')
        //     ->setHelp('This command allows you to specify a module to work on locally');

        // $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'name of the module (Magento module name, MyCompany_MyModule)');
        // $this->addOption('composer-package-name', null, InputOption::VALUE_OPTIONAL, 'composer-package-name', null);
        // $this->addOption('directory', null, InputOption::VALUE_OPTIONAL, 'directory', null);



    }

    /**
     * Undocumented function
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return integer
     * 
     * @see vendor/composer/composer/src/Composer/Command/StatusCommand.php
     * 
     * The next step is to add the option to specify a module name, we can then create our own
     * diff/change generator that would be a patch/git diff and generate a patch file from it.
     * see: vendor/composer/composer/src/Composer/Downloader/FileDownloader.php:getLocalChanges()
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $composerApplication = new ComposerApplication();
        $composer = $composerApplication->getComposer(true, null, null);
        $io = $composerApplication->getIO();

        $installedRepo = $composer->getRepositoryManager()->getLocalRepository();
        $dm = $composer->getDownloadManager();
        $im = $composer->getInstallationManager();
        $errors = array();
        $unpushedChanges = array();
        $vcsVersionChanges = array();
        $parser = new VersionParser;
        $guesser = new VersionGuesser($composer->getConfig(), new ProcessExecutor($io), $parser);
        $dumper = new ArrayDumper;

        $moduleChanges = [];

        // list packages
        /** @var \Composer\Package\CompletePackage $package */
        foreach ($installedRepo->getCanonicalPackages() as $package) {
            $downloader = $dm->getDownloaderForPackage($package);
            $targetDir = $im->getInstallPath($package);

            if ($downloader instanceof ChangeReportInterface) {
                if (is_link($targetDir)) {
                    $errors[$targetDir] = $targetDir . ' is a symbolic link.';
                }

                if ($changes = $downloader->getLocalChanges($package, $targetDir)) {
                    if(!isset($moduleChanges[$package->getName()])){
                        $moduleChanges[$package->getName()] = [];    
                    }
                    $changes = explode(PHP_EOL, $changes);
                    foreach($changes as $change){
                        $trimmed = trim($change);
                        if($trimmed){
                            $moduleChanges[$package->getName()][] = $trimmed;
                        }
                    }
                }
            }

            if ($downloader instanceof VcsCapableDownloaderInterface) {
                if ($downloader->getVcsReference($package, $targetDir)) {
                    switch ($package->getInstallationSource()) {
                        case 'source':
                            $previousRef = $package->getSourceReference();
                            break;
                        case 'dist':
                            $previousRef = $package->getDistReference();
                            break;
                        default:
                            $previousRef = null;
                    }

                    $currentVersion = $guesser->guessVersion($dumper->dump($package), $targetDir);

                    if ($previousRef && $currentVersion && $currentVersion['commit'] !== $previousRef) {
                        $vcsVersionChanges[$targetDir] = array(
                            'previous' => array(
                                'version' => $package->getPrettyVersion(),
                                'ref' => $previousRef,
                            ),
                            'current' => array(
                                'version' => $currentVersion['pretty_version'],
                                'ref' => $currentVersion['commit'],
                            ),
                        );
                    }
                }
            }

            if ($downloader instanceof DvcsDownloaderInterface) {
                if ($unpushed = $downloader->getUnpushedChanges($package, $targetDir)) {
                    $unpushedChanges[$targetDir] = $unpushed;
                }
            }
        }

        // output errors/warnings
        if (!$moduleChanges && !$errors && !$unpushedChanges && !$vcsVersionChanges) {
            $output->writeln('No changes detected');
            return 0;
        }

        if($moduleChanges){
            foreach($moduleChanges as $moduleName => $changes){
                $output->writeln($moduleName.' ('.count($changes).' differences):');
                foreach($changes as $change){
                    $output->writeln('    '.$change);
                }
                $output->writeln('');
            }
        }
        return 0;

        print_r($errors);
        echo '~~~~~~~~~~~~~~~~~~~~~~~~~'.PHP_EOL;
        print_r($moduleChanges);
        echo '--------------'.PHP_EOL;
        print_r($unpushedChanges);
        echo '================'.PHP_EOL;
        print_r($vcsVersionChanges);


        // return this if there was no problem running the command
        return 0;

        // or return this if some error happened during the execution
        // return 1;
    }
}