<?php

namespace Zero1\MagentoDev\Command\Make;

use DOMDocument;
use DOMXPath;
use JsonPath\JsonObject;
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
use Spatie\ArrayToXml\ArrayToXml;
use Zero1\MagentoDev\Service\Module;
use Zero1\MagentoDev\Service\Magento\Routes;
use Zero1\MagentoDev\Service\Magento\Events;
use Vyuldashev\XmlToArray\XmlToArray;
use PrettyXml\Formatter;

class Observer extends Command
{
    public const OPTION_KEY_MODULE_NAME = 'name';

    public const OPTION_KEY_AREA = 'area';

    public const OPTION_KEY_EVENTNAME = 'event';

    public const OPTION_KEY_FORCE = 'force';

    protected static $defaultName = 'make:observer';

    /** @var Filesystem */
    protected $filesystem;

    protected Module $module;

    protected Routes $routes;

    protected Events $events;

    public function __construct(
        Module $module,
        Routes $routes,
        Events $events
    ) {
        $this->module = $module;
        $this->routes = $routes;
        $this->events = $events;
        $this->filesystem = new Filesystem();
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Create an observer')
            ->setHelp('This command allows you to create an observer for a specific event within a module');

        $this->addOption(self::OPTION_KEY_MODULE_NAME, null, InputOption::VALUE_REQUIRED, 'Name of the module (Magento module name, MyCompany_MyModule)');
        $this->addOption(self::OPTION_KEY_AREA, null, InputOption::VALUE_REQUIRED, 'Area ('.implode(', ',Events::AREAS).'), provide comma separated list for multiple.');
        $this->addOption(self::OPTION_KEY_EVENTNAME, null, InputOption::VALUE_REQUIRED, 'Event name to listen to');
        $this->addOption(self::OPTION_KEY_FORCE, null, InputOption::VALUE_NONE, 'Overwrite files without asking.');
    }

    protected function getOption(InputInterface $input, OutputInterface $output, $name, array $choices = null, $multiSelect = false)
    {
        /** @var \Symfony\Component\Console\Input\InputOption $option */
        $option = $this->getDefinition()->getOption($name);

        $value = $input->getOption($name);
        $helper = $this->getHelper('question');
        if(!$choices){
            $question = new Question($option->getDescription().': ', $option->getDefault());
            // TODO add some validation
            while(!$value){
                $value = trim(
                    (string)$helper->ask(
                        $input, 
                        $output, 
                        $question
                    )
                );
            }
        }else{
            $question = new ChoiceQuestion($option->getDescription().': ', $choices, $option->getDefault());
            if($multiSelect){
                $question->setMultiselect(true);
            }
            
            // TODO add some validation
            while(!$value){
                if($multiSelect){
                    $value = $helper->ask(
                        $input, 
                        $output, 
                        $question
                    );
                }else{
                    $value = trim(
                        (string)$helper->ask(
                            $input, 
                            $output, 
                            $question
                        )
                    );
                }
            }
        }
        
        return $value;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $moduleName = $this->getOption($input, $output, self::OPTION_KEY_MODULE_NAME);
        $module = $this->module->locate($moduleName);

        $areas = $this->getOption($input, $output, self::OPTION_KEY_AREA, Events::AREAS, true);
        if(!is_array($areas)){
            $areas = explode(',', $areas);
        }

        $event = strtolower($this->getOption($input, $output, self::OPTION_KEY_EVENTNAME));
        $force = (bool)$input->getOption('force');

        $observerName = strtolower($module->getName().'_'.$event);
        $observerClassName = str_replace('_', '', ucwords($event, '_'));
        $observerFilePath = $module->getBaseDirectory().'/Observer/'.$observerClassName.'.php';
        $observerClassPath = $module->getNamespace().'Observer\\'.$observerClassName;

        if(in_array(Events::AREA_GLOBAL, $areas)){
            $areas = [Events::AREA_GLOBAL];
        }
        foreach($areas as $area){
            if(!$force && $this->events->hasObserver($module, $event, $area, $observerName)){
                $output->writeln('<error>Observer already configured, specify --force to overwrite </error>');
                return 1;
            }
            $this->events->addObserver($module, $event, $area, $observerName, $observerClassPath, false);
            $output->writeln('Observer configured for '.$area);
        }

        if(!$force && $this->filesystem->exists($observerFilePath)){
            $output->writeln('<error>Observer class already exists, specify --force to overwrite </error>');
            return 1;
        }

        $this->filesystem->dumpFile(
            $observerFilePath,
            $this->getMustacheEngine()->render(
                'Observer/observer.php',  
                [
                    'namespace' => $module->getNamespace(),
                    'module_name' => $module->getName(),
                    'event_name' => $event,
                    'className' => $observerClassName,
                ]
            )
        );
        $output->writeln('File written: '.$observerFilePath);

        $output->writeln('<info>Done</info>');

        $output->writeln('You may need to run php bin/magento cache:flush');
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

    /**
     * TODO - move this
     *
     * @return Mustache_Engine
     */
    protected function getMustacheEngine()
    {
        $m = new Mustache_Engine(array(
            'entity_flags' => ENT_QUOTES,
            'loader' => new Mustache_Loader_FilesystemLoader(dirname(__FILE__) . '/../../../var/templates'),
            'helpers' => [
                'lower_case' => function($string = null, \Mustache_LambdaHelper $render = null){
                    if($render){
                        $string = $render($string);
                    }
                    return strtolower($string);
                }
            ]
        ));
        return $m;
    }

    protected function normalize(&$config)
    {
        $keys = array_keys($config);
        foreach($keys as $key){
            if(strpos($key, '_') === 0){
                continue;
            }
            $value = $config[$key];
            if(!is_array($value)){
                continue;
            }
            $subKey = array_key_first($value);
            if(!is_numeric($subKey)){
                $config[$key] = [$value];
            }
            foreach($config[$key] as &$value){
                $this->normalize($value);
            }
        }
    }

    protected function merge(&$existing, $new)
    {
        // foreach($new as $newKey => $newValue){
        //     if(!isset($existing[$newKey])){
        //         $existing[$newKey] = $newValue
        //     }else{
        //         foreach($existing[$newKey] as $existingValue){
        //             if($existingValue['_attributes']['id'] == $newKey['_attributes']['id']){

        //             }
        //         }
        //     }
        // }
    }
}