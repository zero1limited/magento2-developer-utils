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

class Module extends Command
{
    protected static $defaultName = 'make:module';

    public function __construct(
    ) {
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



    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
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
        echo 'composer package nane: '.$composerPackage.PHP_EOL;
        $context['composer_package_name'] = $composerPackage;
        $context['composer_psr4'] = $magentoModuleCompanyName.'\\\\'.$magentoModulePackageName.'\\\\';
        
        $directory = $input->getOption('directory');
        if(!$directory){
            $directories = [
                $this->getExtensionsDirectory($composerPackage),
                $this->getAppCodeDirectory($moduleName),
                'custom',
            ];

            $question = new ChoiceQuestion(
                'Directory (defaults to 0): ',
                $directories,
                0
            );
            $question->setErrorMessage('Option %s is invalid.');

            $directory = $helper->ask($input, $output, $question);
            if($directory === 'custom'){
                $question = new Question('Please enter the directory path (path/to/my-company/my-module): ', null);
                $directory = trim($helper->ask($input, $output, $question));
                if(!$directory){
                    $output->writeln('You need to specify a path to the module');
                    return 1;
                }
            }
        }
        echo 'directory: '.json_encode($directory).PHP_EOL;


        // actually install the module
        if(!is_dir($directory)){
            echo 'making directory'.PHP_EOL;
            mkdir($directory, 0777, true);
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
            $filepath = $directory.'/'.$file;
            if(!is_dir(dirname($filepath))){
                mkdir(dirname($filepath), 0777, true);
            }
            echo 'writing '.$filepath.PHP_EOL;
            file_put_contents($filepath, $mustache->render($file, $context));
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