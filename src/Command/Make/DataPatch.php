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

class DataPatch extends Command
{
    protected static $defaultName = 'make:data-patch';

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
        $this->setDescription('Add a data patch to a module')
            ->setHelp('This command will create a data patch for a Magento module');

        $this->addOption('module', null, InputOption::VALUE_REQUIRED, 'name of the module (Magento module name, MyCompany_MyModule)');
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'name of the patch', null);
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

        $patchName = $input->getOption('name');
        if(!$patchName){
            $question = new Question('Please enter the name of the data ("add data to categories"): ', null);
            $patchName = trim($helper->ask($input, $output, $question));
        }
        if(!$patchName){
            $output->writeln('You need to specify a name for the data patch');
            return 1;
        }

        $overwrite = (bool)$input->getOption('overwrite');

        $context['magento_module_name'] = $moduleName;
        $context['namespace'] = $module->getNamespace();
        $context['className'] = str_replace(' ', '', ucwords($patchName));
        $context['patch_name'] = $context['className'].'.php';

        // actually create the patch
        $patchDirectory = $module->getBaseDirectory().'/Setup/Patch/Data';
        if(!$this->filesystem->exists($patchDirectory)){
            $this->filesystem->mkdir($patchDirectory, 0777);
        }

        $patchPath = $patchDirectory.'/'.$context['patch_name'];
        if($this->filesystem->exists($patchPath) && !$overwrite){
            $output->writeln('Patch already exists: '.$patchPath);
            $question = new ConfirmationQuestion('Overwrite [Y/n]?', false);
            if (!$helper->ask($input, $output, $question)) {
                return 1;
            }
        }

        $this->templateRenderer->writeTemplate(
            'Setup/Patch/Data.php.mustache', 
            $patchPath, 
            $context
        );
        $output->writeln('Written: '.$patchPath);
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