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
use Zero1\MagentoDev\Service\TemplateRenderer;

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
        GitService $gitService,
        protected TemplateRenderer $templateRenderer
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
        $this->addOption('overwrite', null, InputOption::VALUE_NONE, 'overwrite existing files');
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
        
        $context['composer_package_name'] = $composerPackage;
        $context['composer_psr4'] = $magentoModuleCompanyName.'\\\\'.$magentoModulePackageName.'\\\\';
        
        $directory = $input->getOption('directory');
        $directories = [
            'extensions' => $this->getExtensionsDirectory($composerPackage),
            'app' => $this->getAppCodeDirectory($moduleName),
            'custom' => 'Custom',
        ];
        if(!$directory){
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
        }else{
            if(!isset($directories[$directory])){
                $output->writeln(
                    'Invalid directory option: '.$directory.'.'
                    .' Was expecting one of: '.implode(', ', array_keys($directories))
                );
                return 1;
            }
            $directoryPath = $directories[$directory];
        }

        // actually install the module
        $overwrite = (bool)$input->getOption('overwrite');
        if(!$this->filesystem->exists($directoryPath)){
            $this->filesystem->mkdir($directoryPath, 0777);
        }else{
            if(!$overwrite){
                $output->writeln('Drectory already exists: '.$directoryPath);
                $question = new ConfirmationQuestion('Overwrite [Y/n]?', true);
                if (!$helper->ask($input, $output, $question)) {
                    return 1;
                }
            }
        }

        $files = [];
        foreach([
            'composer.json',
            '.gitignore',
            'README.md',
            'registration.php',
            'etc/module.xml'
        ] as $file){
            $files[$file] = $directoryPath.'/'.$file;
        }

        if($overwrite){
            $this->templateRenderer->writeTemplates($files, $context);
            foreach($files as $template => $outputFilepath){
                $output->writeln('Written: '.$outputFilepath);
            }
        }else{
            foreach($files as $template => $outputFilepath){
                if(!$this->filesystem->exists($outputFilepath)){
                    $this->templateRenderer->writeTemplate($template, $outputFilepath, $context);
                }else{
                    $output->writeln('File already exists: '.$outputFilepath);
                    $question = new ConfirmationQuestion('Overwrite [Y/n]?', false);
                    if (!$helper->ask($input, $output, $question)) {
                        continue;
                    }
                    $this->templateRenderer->writeTemplate($template, $outputFilepath, $context);
                    $output->writeln('Written: '.$outputFilepath);
                }
            }
        }
        
        if($directory == 'extensions' || $directory == 'custom'){

            $output->write('Configuring composer repository...');
            $this->composerService->addRepository($composerPackage, [
                'type' => 'path', 
                'url' => $directoryPath,
                'options' => [
                    'symlink' => true,
                ]
            ]);
            $output->writeln('OK');

            $output->write('Requiring composer package...');
            try{
                $response = $this->composerService->require($composerPackage, '@dev');
            }catch(\RuntimeException $e){
                $output->writeln('<error>'.$e->getMessage().'</error>');
                return $e->getCode();
            }
            $output->writeln('OK');
        }
        
        return 0;
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
}