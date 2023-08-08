<?php

declare(strict_types=1);

namespace Zero1\MagentoDev\Command\Module;

use Minicli\Command\CommandController;
use Zero1\MagentoDev\CommandSummaryInterface;
use Minicli\App;
use Minicli\Input;

class CreateController extends CommandController implements CommandSummaryInterface
{
    protected const LOCATION_KEY = 'location';
    protected const LOCATION_APP = 'app';
    protected const LOCATION_EXT = 'ext';

    /**
     * Return a summary of the command
     * 
     * @param App $app
     *
     * @return void
     */
    public function getSummary(App $app): void
    {
        $app->getPrinter()->out('module create - create a new Magento module');
    }

    public function handle(): void
    {
        $configuration = $this->configure();
        
        if($configuration[self::LOCATION_KEY] == self::LOCATION_EXT){
            $this->createExtensionsDirectory();
            $this->addRepositoryToComposerJson();
            $this->createSkeleton();
        }
    }

    protected function configure()
    {
        return [
            self::LOCATION_KEY => $this->determineParam(self::LOCATION_KEY, self::LOCATION_EXT, 'Where would you like to create the module?', [
                self::LOCATION_EXT => 'in the ./extensions directory',
                self::LOCATION_APP => 'in the ./app/code directory',
            ]),
        ];
    }

    protected function determineParam(
        $key,
        $defaultValue,
        $userPrompt,
        $values = []
    ){
        $value = $defaultValue;
        if($this->hasParam($key)){
            $value = $this->getParam($key);
        }else{
            $this->getPrinter()->out($userPrompt, 'bold');
            $this->getPrinter()->newline();
            if(!empty($values)){
                $this->getPrinter()->out('Value options are:');
                $this->getPrinter()->newline();
                foreach($values as $valueOption => $valueDescription){
                    $this->getPrinter()->out(sprintf('%s - %s', $valueOption, $valueDescription));
                    $this->getPrinter()->newline();
                }
                $input = new Input('>');
                $value = $input->read();
            }
        }
        return $value;
    }
}
