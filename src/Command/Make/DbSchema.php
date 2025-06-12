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
use Zero1\MagentoDev\Service\Module as ModuleService;

class DbSchema extends Command
{
    protected static $defaultName = 'make:db-schema';

    /** @var Filesystem */
    protected $filesystem;

    public function __construct(
        protected TemplateRenderer $templateRenderer,
        protected ModuleService $moduleService
    ) {
        $this->filesystem = new Filesystem();
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Add db_schema.xml to a module')
            ->setHelp('This command will create a db_schema.xml for a Magento module');

        $this->addOption('module', null, InputOption::VALUE_REQUIRED, 'name of the module (Magento module name, MyCompany_MyModule)');
        $this->addOption('overwrite', null, InputOption::VALUE_NONE, 'overwrite existing files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $context = [];

        $moduleName = $input->getOption('module');
        if(!$moduleName){
            $question = new Question('Please enter the name of the module (MyCompany_MyModule): ', null);
            $moduleName = trim($helper->ask($input, $output, $question));
        }
        if(!$moduleName){
            $output->writeln('You need to specify a Magento module name');
            return 1;
        }
        try{
            $module = $this->moduleService->locate($moduleName);
        }catch(\Exception $e){
            $output->writeln('Error: '.$e->getMessage());
            return $e->getCode() > 0? $e->getCode() : 1;
        }

        $overwrite = (bool)$input->getOption('overwrite');

        // actually create the patch
        $directory = $module->getBaseDirectory().'/etc';
        if(!$this->filesystem->exists($directory)){
            $this->filesystem->mkdir($directory, 0777);
        }

        $schemaPath = $directory.'/db_schema.xml';
        if($this->filesystem->exists($schemaPath) && !$overwrite){
            $output->writeln('db_schema.xml already exists: '.$schemaPath);
            $question = new ConfirmationQuestion('Overwrite [Y/n]?', false);
            if (!$helper->ask($input, $output, $question)) {
                return 1;
            }
        }

        $this->templateRenderer->writeTemplate(
            'etc/db_schema.xml.mustache', 
            $schemaPath, 
            $context
        );
        $output->writeln('Written: '.$schemaPath);
        $output->writeln('Once modified you will need to run:');
        $output->writeln('php bin/magento setup:db-declaration:generate-whitelist --module-name='.$module->getName());
        $output->writeln('OK');
        
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