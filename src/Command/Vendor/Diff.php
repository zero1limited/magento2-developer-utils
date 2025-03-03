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
use Zero1\MagentoDev\Composer\Downloader\ZipDownloader;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Filesystem\Filesystem;
use mikehaertl\shellcommand\Command as ShellCommand;

class Diff extends Command
{
    public const DIFF_ADDED = 'added';
    public const DIFF_REMOVED = 'removed';
    public const DIFF_CHANGED = 'changed';

    protected static $defaultName = 'vendor:diff';

    /** @var Filesystem */
    protected $filesystem;

    public function __construct(
    ) {
        parent::__construct();
        $this->filesystem = new Filesystem();
    }

    protected function configure(): void
    {
        $this->setDescription('Show changes in the vendor directory and optionally generate a patch for the changes.')
            ->setHelp('This command allows you to view files changes in the vendor directory, as well as generate a patch if required');

        $this->addOption('package', null, InputOption::VALUE_OPTIONAL, 'vendor package to examine eg magento/framework, if not provided a general summary of all modules in the vendor directory will be provided.', null);
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
        // setup stuff
        $output->getFormatter()->setStyle(self::DIFF_ADDED, new OutputFormatterStyle('green', 'default', ['bold']));
        $output->getFormatter()->setStyle(self::DIFF_CHANGED, new OutputFormatterStyle('blue', 'default', ['bold']));
        $output->getFormatter()->setStyle(self::DIFF_REMOVED, new OutputFormatterStyle('red', 'default', ['bold']));

        $output->getFormatter()->setStyle('i', new OutputFormatterStyle('yellow', 'default', ['bold']));
        $output->getFormatter()->setStyle('x', new OutputFormatterStyle('red', 'white', ['bold']));

        $composerApplication = new ComposerApplication();
        $composer = $composerApplication->getComposer(true, null, null);
        $io = $composerApplication->getIO();

        $this->overrideDownloaders($composer, $io);

        $package = $input->getOption('package');
        if(!$package){
            return $this->executeSummary($input, $output, $composer);
        }{
            return $this->executeDetail($input, $output, $composer);
        }
    }

    protected function overrideDownloaders(
        \Composer\Composer $composer,
        \Composer\IO\IOInterface $io
    ): void
    {
        $dm = $composer->getDownloadManager();
        $config = $composer->getConfig();
        $process = $composer->getLoop()->getProcessExecutor();
        $cache = null;
        if ($config->get('cache-files-ttl') > 0) {
            $cache = new \Composer\Cache($io, $config->get('cache-files-dir'), 'a-z0-9_./');
            $cache->setReadOnly($config->get('cache-read-only'));
        }
        $fs = new \Composer\Util\Filesystem($process);
        $dm->setDownloader(
            'zip',
            new ZipDownloader(
                $io,
                $config,
                $composer->getLoop()->getHttpDownloader(),
                $composer->getEventDispatcher(),
                $cache,
                $fs,
                $process
            )
        );
    }

    protected function executeSummary(
        InputInterface $input, 
        OutputInterface $output,
        \Composer\Composer $composer
    ): int
    {
        $installedRepo = $composer->getRepositoryManager()->getLocalRepository();
        $im = $composer->getInstallationManager();
        $dm = $composer->getDownloadManager();

        $errors = [];
        $moduleChanges = [];

        // list packages
        $installedPackages = $installedRepo->getCanonicalPackages();
        $installedPackagesCount = count($installedPackages);
        $processedCount = 0;

        /** @var \Composer\Package\CompletePackage $package */
        foreach ($installedPackages as $package) {
            $processedCount++;
            $output->write(sprintf(
                '[%d/%d - %d%%] %s', 
                $processedCount, 
                $installedPackagesCount, 
                floor(($processedCount/$installedPackagesCount)*100),
                $package->getName()
            ));
            /** @var \Composer\Downloader\ZipDownloader $downloader */
            /** @var \Zero1\MagentoDev\Composer\Downloader\ZipDownloader $downloader */
            $downloader = $dm->getDownloaderForPackage($package);
                        
            
            $targetDir = $im->getInstallPath($package);

            if ($downloader instanceof ChangeReportInterface) {
                if (is_link($targetDir)) {
                    $errors[$targetDir] = $targetDir . ' is a symbolic link.';
                }

                if ($changes = $downloader->getLocalChanges($package, $targetDir)) {
                    $comparer = $downloader->getComparer();
                    $changes = $comparer->getChanged();
                    $downloader->setComparer(null);

                    $moduleChanges[$package->getName()] = array_merge([
                        self::DIFF_ADDED => [],
                        self::DIFF_REMOVED => [],
                        self::DIFF_CHANGED => [],
                    ], $changes);
                }
            }

            // console helper not available in this version
            echo "\033[2K\r";
        }

        // output errors/warnings
        if (!$moduleChanges && !$errors) {
            $output->writeln('No changes detected');
            return 0;
        }

        if($moduleChanges){
            foreach($moduleChanges as $moduleName => $changes){

                $changesCount = count($changes[self::DIFF_ADDED]) 
                    + count($changes[self::DIFF_REMOVED]) 
                    + count($changes[self::DIFF_CHANGED]);

                $output->writeln($moduleName.' ('.$changesCount.' differences):');

                foreach([
                    self::DIFF_ADDED,
                    self::DIFF_REMOVED,
                    self::DIFF_CHANGED,
                ] as $modification){
                    foreach($changes[$modification] as $filepath){
                        $output->writeln(sprintf(
                            '<%s>%s</%s>%s%s',
                            $modification,
                            $modification,
                            $modification,
                            str_repeat(' ', (9 - strlen($modification))),
                            $filepath
                        ));
                    }
                }
                $output->writeln('');
            }
        }
        return 0;
    }

    protected function executeDetail(
        InputInterface $input, 
        OutputInterface $output,
        \Composer\Composer $composer
    ): int
    {
        $packageName = $input->getOption('package');
        $installedRepo = $composer->getRepositoryManager()->getLocalRepository();
        $im = $composer->getInstallationManager();
        $dm = $composer->getDownloadManager();

        $errors = [];
        $moduleChanges = [];

        /** @var \Composer\Package\CompletePackage $package */
        $package = $installedRepo->findPackage($packageName, '*');
        if(!$package){
            $output->writeln('<error>Unable to find package: </error>'.$packageName);
            return 1;
        }


        /** @var \Composer\Downloader\ZipDownloader $downloader */
        /** @var \Zero1\MagentoDev\Composer\Downloader\ZipDownloader $downloader */
        $downloader = $dm->getDownloaderForPackage($package);
        $targetDir = $im->getInstallPath($package);

        if ($downloader instanceof ChangeReportInterface) {
            if (is_link($targetDir)) {
                $output->writeln('<error>'.$targetDir . ' is a symbolic link.</error>');
                return 1;
            }

            $downloader->setComparer(new \Zero1\MagentoDev\Composer\Package\Comparer\Comparer());
            if ($changes = $downloader->getLocalChanges($package, $targetDir)) {

                /** @var \Zero1\MagentoDev\Composer\Package\Comparer\Comparer $comparer */
                $comparer = $downloader->getComparer();
                $changes = $comparer->getChanged(false, false);
                $downloader->setComparer(null);
                $moduleChanges = array_merge([
                    self::DIFF_ADDED => [],
                    self::DIFF_REMOVED => [],
                    self::DIFF_CHANGED => [],
                ], $changes);

                $output->writeln($comparer->getPatch(true));
            }
        }

        // output errors/warnings
        if (!$moduleChanges && !$errors) {
            $output->writeln('No changes detected');
            return 0;
        }

        if($moduleChanges){
            $changesCount = count($moduleChanges[self::DIFF_ADDED]) 
                + count($moduleChanges[self::DIFF_REMOVED]) 
                + count($moduleChanges[self::DIFF_CHANGED]);

            $output->writeln($packageName.' ('.$changesCount.' differences):');

            foreach([
                self::DIFF_ADDED,
                self::DIFF_REMOVED,
                self::DIFF_CHANGED,
            ] as $modification){
                foreach($moduleChanges[$modification] as $filepath){
                    $output->writeln(sprintf(
                        '<%s>%s</%s>%s%s',
                        $modification,
                        $modification,
                        $modification,
                        str_repeat(' ', (9 - strlen($modification))),
                        $filepath
                    ));
                }
            }
            $output->writeln('');

            $helper = $this->getHelper('question');
            if($helper->ask($input, $output, new ConfirmationQuestion('Would you like to generate a patch? (y/n): ', false))){
                
                $fileExclusions = [];
                if($helper->ask($input, $output, new ConfirmationQuestion('Would you like to exclude some files from the patch? (y/n): ', false))){
                    foreach([
                        self::DIFF_ADDED,
                        self::DIFF_REMOVED,
                        self::DIFF_CHANGED,
                    ] as $modification){
                        foreach($moduleChanges[$modification] as $filepath){
                            $output->write(sprintf(
                                '<%s>%s</%s>%s%s',
                                $modification,
                                $modification,
                                $modification,
                                str_repeat(' ', (9 - strlen($modification))),
                                $filepath
                            ));

                            $choiceQuestion = new ChoiceQuestion(
                                '',
                                [
                                    'i' => 'include',
                                    'x' => 'exclude',
                                ],
                                'i'
                            );
                            $choiceQuestion->setPrompt('default: i (include): ');
                            $choice = $helper->ask($input, $output, $choiceQuestion);
                            
                            $this->clearLines(3);

                            $output->writeln(sprintf(
                                '<%s>%s</%s> <%s>%s</%s>%s%s',
                                $choice,
                                $choice == 'x'? 'exclude' : 'include',
                                $choice,
                                $modification,
                                $modification,
                                $modification,
                                str_repeat(' ', (9 - strlen($modification))),
                                $filepath
                            ));

                            if($choice == 'x'){
                                $fileExclusions[] = $filepath;
                            }
                        }
                    }
                }

                do{
                    $name = trim($helper->ask($input, $output, new Question('What would you like to name this patch? (this will become the filename of the patch e.g "Security Fix 0001"): ', null)));
                }while(!$name);
                $filepath = 'patches/'.preg_replace('/[^a-z0-9]+/', '-', strtolower($name)).'.patch';
                $this->clearLines(1);
                $output->writeln('Name: '.$name.' (filepath: '.$filepath.')');
                $description = trim($helper->ask($input, $output, new Question('Short description (e.g this fixes issue when logging in from browser x): ', null)));
                
                if($description){
                    $output->writeln('Description: '.$description);
                }
                $composerDescription = $name.($description? ' - '.$description : '');

                $comparer = new \Zero1\MagentoDev\Composer\Package\Comparer\Comparer();
                $comparer->setFileExclusions($fileExclusions);
                $downloader->setComparer($comparer);
                $downloader->getLocalChanges($package, $targetDir);

                /** @var \Zero1\MagentoDev\Composer\Package\Comparer\Comparer $comparer */
                $comparer = $downloader->getComparer();
                $downloader->setComparer(null);

                $patchContent = $comparer->getPatch();
                if(!$this->filesystem->exists(dirname($filepath))){
                    $this->filesystem->mkdir(dirname($filepath), 0777);
                }
                $this->filesystem->dumpFile($filepath, $patchContent);

                $composerConfigureCommand = new ShellCommand(sprintf(
                    'composer config --json --merge extra.patches.%s \'{"%s": "%s"}\'',
                    $package->getName(),
                    $composerDescription,
                    $filepath
                ));
                $composerConfigureCommand->execute();

                if($composerConfigureCommand->getExitCode()){
                    echo 'EORORORORORO'.PHP_EOL;
                    echo $composerConfigureCommand->getOutput().PHP_EOL;
                    return $composerConfigureCommand->getExitCode();
                }

                $output->writeln('<success>Patch created and configured!</success>');
                $output->writeln('You will need to run `composer install` to apply the patch. (Caution: this will discard all changes in this directory)');                
            }
        }
        return 0;
    }

    public const CURSOR_MOVE_UP = "\033[1A";
    public const CURSOR_CLEAR_LINE = "\033[K";
    public const CURSOR_RETURN_TO_BEGINNING_OF_LINE = "\r";

    protected function clearLine()
    {
        echo self::CURSOR_RETURN_TO_BEGINNING_OF_LINE;
        echo self::CURSOR_CLEAR_LINE;
    }

    protected function clearLineAndReturn()
    {
        $this->clearLine();
        echo self::CURSOR_MOVE_UP;
    }

    protected function clearLines($x = 1)
    {
        for($i = 0; $i <= $x; $i++){
            $this->clearLineAndReturn();
        }
    }
}