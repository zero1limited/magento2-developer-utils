<?php

namespace Zero1\MagentoDev\Command\Make;

use DOMDocument;
use DOMXPath;
use JsonPath\JsonObject;
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
use Vyuldashev\XmlToArray\XmlToArray;
use PrettyXml\Formatter;

class Controller extends Command
{
    public const OPTION_KEY_MODULE_NAME = 'name';
    public const OPTION_KEY_AREA = 'area';
    public const OPTION_VALUE_AREA_FRONTEND = 'frontend';
    public const OPTION_VALUE_AREA_ADMIN = 'adminhtml';
    public const OPTION_VALUE_AREA_REST_API = 'rest-api';
    public const OPTION_VALUES_AREA = [
        self::OPTION_VALUE_AREA_FRONTEND,
        self::OPTION_VALUE_AREA_ADMIN,
        self::OPTION_VALUE_AREA_REST_API
    ];
    public const OPTION_KEY_FRONTNAME = 'frontname';
    public const OPTION_KEY_CONTROLLER = 'controller';
    public const OPTION_KEY_ACTION = 'action';
    public const OPTION_KEY_RESPONSE_TYPE = 'response-type';
    public const OPTION_VALUE_RESPONSE_TYPE_HTML = 'html';
    public const OPTION_VALUE_RESPONSE_TYPE_JSON = 'json';
    public const OPTION_VALUE_RESPONSE_TYPE_TXT = 'txt';
    public const OPTION_VALUES_RESPONSE_TYPE = [
        self::OPTION_VALUE_RESPONSE_TYPE_HTML,
        self::OPTION_VALUE_RESPONSE_TYPE_JSON,
        self::OPTION_VALUE_RESPONSE_TYPE_TXT,
    ];
    public const OPTION_KEY_REQUEST_METHOD = 'http-method';
    public const OPTION_VALUE_REQUEST_METHOD_GET = 'GET';
    public const OPTION_VALUE_REQUEST_METHOD_POST = 'POST';
    public const OPTION_VALUES_REQUEST_METHOD = [
        self::OPTION_VALUE_REQUEST_METHOD_GET,
        self::OPTION_VALUE_REQUEST_METHOD_POST,
    ];
    public const OPTION_KEY_FORCE = 'force';

    protected static $defaultName = 'make:controller';

    /** @var Filesystem */
    protected $filesystem;

    protected Module $module;

    protected Routes $routes;

    public function __construct(
        Module $module,
        Routes $routes
    ) {
        $this->module = $module;
        $this->routes = $routes;
        $this->filesystem = new Filesystem();
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Create a controller')
            ->setHelp('This command allows you to create a controller within a module');

        $this->addOption(self::OPTION_KEY_MODULE_NAME, null, InputOption::VALUE_REQUIRED, 'Name of the module (Magento module name, MyCompany_MyModule)');
        $this->addOption(self::OPTION_KEY_AREA, null, InputOption::VALUE_REQUIRED, 'Area ('.implode(', ',self::OPTION_VALUES_AREA).')');
        $this->addOption(self::OPTION_KEY_FRONTNAME, null, InputOption::VALUE_REQUIRED, 'Front name of the controller (e.g https://www.example.com/FRONTNAME/controller/action)');
        $this->addOption(self::OPTION_KEY_CONTROLLER, null, InputOption::VALUE_REQUIRED, 'Controller (e.g https://www.example.com/frontname/CONTROLLER/action)');
        $this->addOption(self::OPTION_KEY_ACTION, null, InputOption::VALUE_REQUIRED, 'Action (e.g https://www.example.com/frontname/controller/ACTION)');
        $this->addOption(self::OPTION_KEY_RESPONSE_TYPE, null, InputOption::VALUE_REQUIRED, 'Response type ('.implode(', ',self::OPTION_VALUES_RESPONSE_TYPE).')');
        $this->addOption(self::OPTION_KEY_REQUEST_METHOD, null, InputOption::VALUE_REQUIRED, 'HTTP Method ('.implode(', ',self::OPTION_VALUES_REQUEST_METHOD).')');
        $this->addOption(self::OPTION_KEY_FORCE, null, InputOption::VALUE_NONE, 'Overwrite files without asking.');
    }

    protected function getOption(InputInterface $input, OutputInterface $output, $name, array $choices = [])
    {
        /** @var \Symfony\Component\Console\Input\InputOption $option */
        $option = $this->getDefinition()->getOption($name);

        $value = $input->getOption($name);
        $helper = $this->getHelper('question');
        if(empty($choices)){
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
            // TODO add some validation
            while(!$value || !in_array($value, $choices)){
                $value = trim(
                    (string)$helper->ask(
                        $input, 
                        $output, 
                        $question
                    )
                );
            }
        }
        
        return $value;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $moduleName = $this->getOption($input, $output, self::OPTION_KEY_MODULE_NAME);
        
        $module = $this->module->locate($moduleName);

        echo $module->getName().' ('.$module->getBaseDirectory().')'.PHP_EOL;

        $area = $this->getOption($input, $output, self::OPTION_KEY_AREA, self::OPTION_VALUES_AREA);
        $helper = $this->getHelper('question');

        echo 'area: '.$area.PHP_EOL;
        if($area != self::OPTION_VALUE_AREA_FRONTEND){
            $output->writeln('area not currently supported, sorry!');
            return 1;
        }

        $frontName = $this->getOption($input, $output, self::OPTION_KEY_FRONTNAME);
        echo 'frontname: '.$frontName.PHP_EOL;

        $route = null;
        $routeConfigExists = false;
        if($this->routes->doesRouteWithFrontNameExist($area, $frontName)){
            $route = $this->routes->getRouteByFrontName($area, $frontName);
            $output->writeln('Frontname already in use by: '.implode(', ', $route['modules']));
            if(!$helper->ask($input, $output, new ConfirmationQuestion('Proceed [Y/n]?: '))){
                return 0;
            }
            if(in_array($module->getName(), $route['modules'])){
                $routeConfigExists = true;
            }
        }

        if($routeConfigExists){
            $output->writeln('route.xml already configured');
        }else{

            $routeXml = $this->getMustacheEngine()->render('etc/route.xml', [
                'id' => $route? $route['id'] : strtolower($frontName),
                'frontName' => $route? null : $frontName,
                'moduleName' => $module->getName(),
            ]);

            $routerXml = $this->getMustacheEngine()->render('etc/router.xml', [
                'id' => 'standard',
                'routes' => [
                    $routeXml
                ]
            ]);

            $routesXml = $this->getMustacheEngine()->render('etc/routes.xml', [
                'routers' => [
                    $routerXml
                ]
            ]);

            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $childDom = new DOMDocument();
            $moduleRoutesXmlPath = $module->getBaseDirectory().'/etc/frontend/routes.xml';
            if($this->filesystem->exists($moduleRoutesXmlPath)){
                $xml = file_get_contents($moduleRoutesXmlPath);
                $dom->loadXML($xml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

                libxml_clear_errors();
                $xpath = new DOMXPath($dom);
                /** @var \DOMNodeList $nodes */
                $nodes = $xpath->query('/config/router[@id="standard"]');
                if(!$nodes){
                    $nodes = $xpath->query('/config');
                    $node = $nodes->item(0);
                    $childDom->loadXML($routerXml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                    $domNode = $dom->importNode($childDom->documentElement, true);
                }else{
                    $node = $nodes->item(0);
                    $childDom->loadXML($routeXml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                    $domNode = $dom->importNode($childDom->documentElement, true);
                }

                $node->appendChild($domNode);
            }else{
                $dom->loadXML($routesXml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                libxml_clear_errors();
                $xpath = new DOMXPath($dom);
            }
            
            $xml = $xpath->document->saveXML(
                $xpath->document->getElementsByTagName('config')[0]
            );
            // echo $xml.PHP_EOL;
            // echo '-====='.PHP_EOL;
            $formatter = new Formatter();
            $formattedXml = $formatter->format(
                '<?xml version="1.0"?>'.PHP_EOL.
                $xml
            );
            // echo $formattedXml.PHP_EOL;
            $this->filesystem->dumpFile($moduleRoutesXmlPath, $formattedXml);
            $output->writeln('File updated: '.$moduleRoutesXmlPath);
        }

        // Sort out the controller
        $controllerName = ucfirst($this->getOption($input, $output, self::OPTION_KEY_CONTROLLER));
        $actionName = ucfirst($this->getOption($input, $output, self::OPTION_KEY_ACTION));
        $requestMethod = $this->getOption($input, $output, self::OPTION_KEY_REQUEST_METHOD, self::OPTION_VALUES_REQUEST_METHOD);
        $responseType = $this->getOption($input, $output, self::OPTION_KEY_RESPONSE_TYPE, self::OPTION_VALUES_RESPONSE_TYPE);
        
        $controllerPath = $module->getBaseDirectory().'/Controller/'.$controllerName.'/'.$actionName.'.php';
        $controllerTemplate = 'Controller/'.strtolower($requestMethod).'_'.strtolower($responseType).'.php';
        $files = [
            $controllerPath => $controllerTemplate,
        ];

        if($responseType == self::OPTION_VALUE_RESPONSE_TYPE_HTML){
            $layoutPath = $module->getBaseDirectory().'/view/frontend/layout/'.strtolower($frontName).'_'.strtolower($controllerName).'_'.strtolower($actionName).'.xml';
            $blockPath = $module->getBaseDirectory().'/Block/'.$controllerName.'/'.$actionName.'.php';
            $templatePath = $module->getBaseDirectory().'/view/frontend/templates/'.strtolower($controllerName).'/'.strtolower($actionName).'.phtml';

            $files[$layoutPath] = 'Controller/layout_html.xml';
            $files[$blockPath] = 'Controller/block.php';
            $files[$templatePath] = 'Controller/template.phtml';
        }

        $templateVars = [
            'namespace' => $module->getNamespace(),
            'controller' => $controllerName,
            'action' => $actionName,
            'frontname' => $frontName,
            'module_name' => $module->getName(),
        ];

        foreach($files as $outputPath => $templatePath){
            $force = false;
            if($this->filesystem->exists($outputPath)){
                // check for forced for each file
                $force = $this->getOption($input, $output, self::OPTION_KEY_FORCE);
            }
            if(!$this->filesystem->exists($outputPath) || $force){
                $this->filesystem->dumpFile(
                    $outputPath,
                    $this->getMustacheEngine()->render($templatePath,  $templateVars)
                );
                $output->writeln('File written: '.$outputPath);
            }
        }

        $output->writeln('<info>Done</info>');

        $output->writeln('You may need to run php bin/magento cache:flush');
        $output->writeln('Controller accessible at '.$requestMethod.' https://www.exmaple.com/'.strtolower($frontName).'/'.strtolower($controllerName).'/'.strtolower($actionName));
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

    // /**
    //  * TODO - move this
    //  *
    //  * @return Mustache_Engine
    //  */
    // protected function getMustacheEngine()
    // {
    //     $m = new Mustache_Engine(array(
    //         'entity_flags' => ENT_QUOTES,
    //         'loader' => new Mustache_Loader_FilesystemLoader(dirname(__FILE__) . '/../../../var/templates'),
    //         'helpers' => [
    //             'lower_case' => function($string = null, ?\Mustache_LambdaHelper $render = null){
    //                 if($render){
    //                     $string = $render($string);
    //                 }
    //                 return strtolower($string);
    //             }
    //         ]
    //     ));
    //     return $m;
    // }

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