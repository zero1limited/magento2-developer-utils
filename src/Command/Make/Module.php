<?php

namespace Zero1\MagentoDev\Command\Make;

use Mustache_Engine;
use Mustache_Loader_FilesystemLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Zero1\MagentoDev\Service\Composer as ComposerService;
use Zero1\MagentoDev\Service\Git as GitService;
use Symfony\Component\Filesystem\Filesystem;
use mikehaertl\shellcommand\Command as ShellCommand;
use Zero1\MagentoDev\Service\Git\UnconfiguredException;

class Module extends Command
{
    protected static $defaultName = 'make:module';

    /** @var ComposerService */
    protected $composerService;

    /** @var GitService */
    protected $gitService;

    /** @var Filesystem */
    protected $filesystem;

    public function __construct(
        ComposerService $composerService,
        GitService $gitService
    ) {
        $this->composerService = $composerService;
        $this->gitService = $gitService;
        $this->filesystem = new Filesystem();
        parent::__construct();
    }

    protected function configure(): void
    {
        // magento module name
        // location (app/code/ / extensions/)
        // [--template-name]
        // [repo]

        $this->setDescription('Setup a module to be worked on locally')
            ->setHelp('This command allows you to specify a module to work on locally');

        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'name of the module (Magento module name, MyCompany_MyModule)');
        $this->addOption('composer-package-name', null, InputOption::VALUE_OPTIONAL, 'composer-package-name', null);
        $this->addOption('directory', null, InputOption::VALUE_OPTIONAL, 'directory', null);
        $this->addOption('repository', null, InputOption::VALUE_OPTIONAL, 'Source control repo location eg git@github.com:org/repo.git. If supplied, the new module will be pushed to the repo and added as a submodule to the current project.', null);


    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->gitService->setOutput($output);
        
        $helper = $this->getHelper('question');
        $context = [];

        $moduleName = $input->getOption('name');
        if(!$moduleName){
            $question = new Question('Please enter the name of the module (MyCompany_MyModule): ', null);
            $moduleName = trim($helper->ask($input, $output, $question));
        }
        if(!$moduleName){
            $output->writeln('You need to specify a Magento module name');
            return 1;
        }
        $context['magento_module_name'] = $moduleName;
        list($magentoModuleCompanyName, $magentoModulePackageName) = explode('_', $moduleName, 2);
        $context['magento_module_company_name'] = $magentoModuleCompanyName;
        $context['magento_module_package_name'] = $magentoModulePackageName;

        $composerPackage = $input->getOption('composer-package-name');
        if(!$composerPackage){
            $composerPackage = $this->getComposerPackageNameFromMagentoModuleName($moduleName);
            $question = new ConfirmationQuestion('Composer Package name: '.$composerPackage.' [Y/n]?', true);

            if (!$helper->ask($input, $output, $question)) {
                $question = new Question('Please enter the composer package name (my-company/my-module): ', null);
                $composerPackage = trim($helper->ask($input, $output, $question));
                if(!$composerPackage){
                    $output->writeln('You need to specify a composer package name');
                    return 1;
                }
            }
        }
        // echo 'composer package nane: '.$composerPackage.PHP_EOL;
        $context['composer_package_name'] = $composerPackage;
        $context['composer_psr4'] = $magentoModuleCompanyName.'\\\\'.$magentoModulePackageName.'\\\\';
        
        $directory = $input->getOption('directory');
        if(!$directory){
            $directories = [
                'extensions' => $this->getExtensionsDirectory($composerPackage),
                'app' => $this->getAppCodeDirectory($moduleName),
                'custom' => 'Custom',
            ];

            $question = new ChoiceQuestion(
                'Directory (default: extensions): ',
                $directories,
                'extensions'
            );
            $question->setErrorMessage('Option %s is invalid.');

            $directory = $helper->ask($input, $output, $question);
            if($directory === 'custom'){
                $question = new Question('Please enter the directory path (path/to/my-company/my-module): ', null);
                $directoryPath = trim($helper->ask($input, $output, $question));
                if(!$directory){
                    $output->writeln('You need to specify a path to the module');
                    return 1;
                }
            }elseif($directory == 'extensions'){
                $directoryPath = $this->getExtensionsDirectory($composerPackage);
            }else{
                $directoryPath = $this->getAppCodeDirectory($moduleName);
            }
        }
        // echo 'directory: '.json_encode($directory).PHP_EOL;
        // echo 'directory path: '.$directoryPath.PHP_EOL;

        // actually install the module
        if(!$this->filesystem->exists($directoryPath)){
            $this->filesystem->mkdir($directoryPath, 0777);
        }else{
            echo 'dir already exists, TODO add a force option (replace or overwrite)'.PHP_EOL;
            die;
        }

        $mustache = $this->getMustacheEngine();
        foreach([
            'composer.json',
            '.gitignore',
            'README.md',
            'registration.php',
            'etc/module.xml'
        ] as $file){
            $filepath = $directoryPath.'/'.$file;
            if(!$this->filesystem->exists(dirname($filepath))){
                $this->filesystem->mkdir(dirname($filepath), 0777);
            }
            
            $this->filesystem->dumpFile($filepath, $mustache->render($file, $context));
        }

        if($directory == 'extensions' || $directory == 'custom'){

            $repo = $input->getOption('repository');
            if(!$repo){
                if($helper->ask($input, $output, new ConfirmationQuestion('Would you like to initialize source control?', false))){
                    $repo = trim($helper->ask($input, $output, new Question('Please enter the repository url (git@github.com:org/repo.git): ', null)));
                }
            }

            if($repo){
                try{
                    $this->gitService->initializeRepository($directoryPath, $repo);
                }catch(UnconfiguredException $e){
                    $output->writeln($e->getMessage());
                    $userName = trim($helper->ask($input, $output, new Question('Please enter you user.name: ', null)));
                    $userEmail = trim($helper->ask($input, $output, new Question('Please enter you user.email: ', null)));
                    $this->gitService->configure($userName, $userEmail);
                    $this->gitService->initializeRepository($directoryPath, $repo);
                }

                $this->filesystem->remove($directoryPath);

                $this->gitService->addSubmodule($repo, $directoryPath, $moduleName);
            }

            $this->composerService->addRepository($composerPackage, [
                'type' => 'path', 
                'url' => $directoryPath,
                'options' => [
                    'symlink' => true,
                ]
            ]);

            $output->writeLn('<info>composer require '.$composerPackage.':@dev</info>');
            $command = new ShellCommand('composer require '.$composerPackage.':@dev');
            echo $command->getOutput().PHP_EOL;
        }


        // return this if there was no problem running the command
        return 0;

        // or return this if some error happened during the execution
        // return 1;
    }

    protected function getComposerPackageNameFromMagentoModuleName($magentoModuleName)
    {
        $magentoModuleName = strtolower(preg_replace('/((?!_).{1})([A-Z])/', '$1-$2', $magentoModuleName));
        list($company, $package) = explode('_', $magentoModuleName, 2);

        return $company.'/'.$package;
    }

    protected function getExtensionsDirectory($composerPackage)
    {
        return 'extensions/'.$composerPackage;
    }

    protected function getAppCodeDirectory($magentoModuleName)
    {
        return 'app/code/'.str_replace('_', '/', $magentoModuleName);
    }

    /**
     * TODO - move this
     *
     * @return Mustache_Engine
     */
    protected function getMustacheEngine()
    {
        $m = new Mustache_Engine(array(
            'loader' => new Mustache_Loader_FilesystemLoader(dirname(__FILE__) . '/../../../var/templates'),
        ));
        return $m;
    }
}