<?php

namespace Zero1\MagentoDev\Command\Make;

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
use Zero1\MagentoDev\Service\Module as ModuleService;
use Symfony\Component\Filesystem\Filesystem;
use mikehaertl\shellcommand\Command as ShellCommand;
use Saloon\XmlWrangler\XmlReader;
use Saloon\XmlWrangler\XmlWriter;
use Mustache_Engine;
use Zero1\MagentoDev\Factory\MustacheFactory;

use function GuzzleHttp\json_decode;

class CliCommand extends Command
{
    protected static $defaultName = 'make:cli-command';

    /** @var Filesystem */
    protected $filesystem;

    /** @var Mustache_Engine */
    protected $mustache;

    /** @var ModuleService */
    protected $moduleService;

    public function __construct(
        MustacheFactory $mustacheFactory,
        ModuleService $moduleService
    ) {
        $this->filesystem = new Filesystem();
        $this->mustache = $mustacheFactory->build();
        $this->moduleService = $moduleService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Add a CLI command to an existing extension.')
            ->setHelp('Add a CLI command to an existing extension.');

        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Name of the module (Magento module name, MyCompany_MyModule)');
        $this->addOption('command-class-name', null, InputOption::VALUE_OPTIONAL, 'Name of the class for the command (MyCommand)', null);
        $this->addOption('command-signature', null, InputOption::VALUE_OPTIONAL, 'The signature of the command. (defaults to example:command)', null);
        $this->addOption('command-help', null, InputOption::VALUE_OPTIONAL, 'The help/description of the command (default "")', null);
        $this->addArgument('--force', InputArgument::OPTIONAL, 'If supplied and class/file exists,  it will be overwritten', false);
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

        try{
            $module = $this->moduleService->locate($moduleName);
        }catch(\Exception $e){
            $output->writeln('Error: '.$e->getMessage());
            return $e->getCode() > 0? $e->getCode() : 1;
        }

        $templateVariables = [
            'namespace' => $module->getNamespace().'Console\\Command',
        ];

        $commandClassName = $input->getOption('command-class-name');
        if(!$commandClassName){
            $question = new Question('Please enter the class name of the command (MyCommand): ', null);
            $commandClassName = trim($helper->ask($input, $output, $question));
        }
        if(!$commandClassName){
            $output->writeln('You need to specify a class name');
            return 1;
        }
        $templateVariables['command-class-name'] = $commandClassName;

        $classFilePath = $module->getNamespacedDirectory().'/Console/Command/'.$commandClassName.'.php';
        $force = (bool)$input->getArgument('--force');
        if($this->filesystem->exists($classFilePath) && !$force){
            $output->writeln(
                sprintf(
                    'file "%s" already exists, either; remove the file, pick a different class name, or specify --force when running this command.',
                    $classFilePath                    
                )
            );
            return 2;
        }

        $commandSignature = $input->getOption('command-signature');
        if(!$commandSignature){
            $question = new Question('Please enter the signature of the command (eg: "some:command:to-run" if blank will default to "example:command"): ', 'example:command');
            $commandSignature = trim($helper->ask($input, $output, $question));
        }
        $templateVariables['command-signature'] = $commandSignature;

        $commandHelp = $input->getOption('command-help');
        if(!$commandHelp){
            $question = new Question('Please enter the help text of the command (eg: "This command does x & y." if blank will default to ""): ', '');
            $commandHelp = trim($helper->ask($input, $output, $question));
        }
        $templateVariables['command-help'] = $commandHelp;
        

        // write out the command php class
        $this->filesystem->dumpFile(
            $classFilePath, 
            $this->mustache->render('Console/Command.php', $templateVariables)
        );
        $output->writeln('generated: '.$classFilePath);

        $itemName = strtolower($moduleName.'_command_'.$commandClassName);
        $classPath = $module->getNamespace().'Console\\Command\\'.$commandClassName;

        $diXmlPath = $module->getBaseDirectory().'/etc/di.xml';
        if(!$this->filesystem->exists($diXmlPath)){
            $this->filesystem->dumpFile(
                $diXmlPath, 
                $this->mustache->render('etc/di.xml', [])
            );
            $output->writeln('generated: '.$diXmlPath);
        }

        $getOrMake = function($element, $key, $matchFn, $createFn){
            $content = $element->getContent();
            $items = isset($content[$key])? $content[$key] : [];
            if($items instanceof \Saloon\XmlWrangler\Data\Element){
                $c = $items->getContent();
                if(is_string($c)){
                    // this is the only <$key> element with no child elements
                    $items = [$items];
                }else{
                    $firstKey = array_key_first($c);
                    if(is_numeric($firstKey)){
                        // there are multiple <$key> elements
                        $items = $items->getContent();
                    }else{
                        // the is the only <$key> element
                        $items = [$items];
                    }
                }
                $content[$key] = $items;
            }
            if(is_string($content) && trim($content) == ''){
                $content = [];
            }

            if($items instanceof \Saloon\XmlWrangler\Data\Element){
                $items = [$items];
                $content[$key] = $items;
            }
            $matchedItem = null;
            foreach($items as $item){
                if($matchFn($item)){
                    $matchedItem = $item;
                    break;
                }
            }
            if(!$matchedItem){
                $matchedItem = $createFn($content);
                $element->setContent($content);
            }
            return $matchedItem;
        };

        
        $diXml = XmlReader::fromFile($diXmlPath);
        $elements = $diXml->elements();
        $configElement = $elements['config'];

        $type = $getOrMake(
            $configElement,
            'type',
            function($element){
                return $element->getAttribute('name') == 'Magento\\Framework\\Console\\CommandList';
            },
            function(&$content){
                $item = \Saloon\XmlWrangler\Data\Element::make()->setAttributes(['name' => 'Magento\\Framework\\Console\\CommandList']);
                $content['type'][] = $item;
                return $item;
            }
        );
        $arguments = $getOrMake(
            $type,
            'arguments',
            function($element){
                return true;
            },
            function(&$content){
                $item = \Saloon\XmlWrangler\Data\Element::make();
                $content['arguments'][] = $item;
                return $item;
            }
        );
        $argument = $getOrMake(
            $arguments,
            'argument',
            function($element){
                return $element->getAttribute('name') == 'commands';
            },
            function(&$content){
                $item = \Saloon\XmlWrangler\Data\Element::make()->setAttributes([
                    'name' => 'commands',
                    'xsi:type' => 'array',
                ]);
                $content['argument'][] = $item;
                return $item;
            }
        );
        $item = $getOrMake(
            $argument,
            'item',
            function($element) use ($itemName) {
                return $element->getAttribute('name') == $itemName;
            },
            function(&$content) use ($itemName) {
                $item = \Saloon\XmlWrangler\Data\Element::make()->setAttributes([
                    'name' => $itemName,
                    'xsi:type' => 'object',
                ]);
                $content['item'][] = $item;
                return $item;
            }
        );
        $item->setContent($classPath);

        /** @var \Saloon\XmlWrangler\Data\RootElement */
        $xml = XmlWriter::make()->write(
            \Saloon\XmlWrangler\Data\RootElement::fromElement('config', $elements['config']),
            $elements['config']->getContent()
        );

        $this->filesystem->dumpFile(
            $diXmlPath,
            $xml
        );
        $output->writeln('updated: '.$diXmlPath);

        $output->writeln('<info>All done</info>');
        $output->writeln('You may need to run `bin/magento cache:flush && bin/magento deploy:mode:set developer`');

        return 0;
    }
}