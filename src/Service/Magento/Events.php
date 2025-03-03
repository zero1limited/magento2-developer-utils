<?php

namespace Zero1\MagentoDev\Service\Magento;

use DOMDocument;
use DOMXPath;
use League\Flysystem\Filesystem;
use Zero1\MagentoDev\Factory\FileSystemFactory;
use Zero1\MagentoDev\Service\Module\UnableToLocateModuleException;
use Zero1\MagentoDev\Service\Magento\ObjectManager;
use Zero1\MagentoDev\Service\TemplateRenderer;
use Zero1\MagentoDev\Model\Magento\Events as EventsModel;
use Zero1\MagentoDev\Model\Magento\Events\Event;
use Zero1\MagentoDev\Model\Magento\Events\Event\Observer;

class Events
{
    public const AREA_GLOBAL = 'global';
    public const AREA_GLOBAL_ETC_PATH  = '/etc/events.xml';
    public const AREA_ADMINHTML = 'adminhtml';
    public const AREA_ADMINHTML_ETC_PATH  = '/etc/adminhtml/events.xml';
    public const AREA_CRONTAB = 'crontab';
    public const AREA_CRONTAB_ETC_PATH  = '/etc/crontab/events.xml';
    public const AREA_FRONTEND = 'frontend';
    public const AREA_FRONTEND_ETC_PATH  = '/etc/frontend/events.xml';
    public const AREA_GRAPHQL = 'graphql';
    public const AREA_GRAPHQL_ETC_PATH  = '/etc/graphql/events.xml';
    public const AREA_WEBAPI_REST = 'webapi_rest';
    public const AREA_WEBAPI_REST_ETC_PATH  = '/etc/webapi_rest/events.xml';
    public const AREA_WEBAPI_SOAP = 'webapi_soap';
    public const AREA_WEBAPI_SOAP_ETC_PATH  = '/etc/webapi_soap/events.xml';

    public const AREAS = [
        self::AREA_GLOBAL,
        self::AREA_ADMINHTML,
        self::AREA_CRONTAB,
        self::AREA_FRONTEND,
        self::AREA_GRAPHQL,
        self::AREA_WEBAPI_REST,
        self::AREA_WEBAPI_SOAP,
    ];

    public const AREAS_ETC_MAP = [
        self::AREA_GLOBAL => self::AREA_GLOBAL_ETC_PATH,
        self::AREA_ADMINHTML => self::AREA_ADMINHTML_ETC_PATH,
        self::AREA_CRONTAB => self::AREA_CRONTAB_ETC_PATH,
        self::AREA_FRONTEND => self::AREA_FRONTEND_ETC_PATH,
        self::AREA_GRAPHQL => self::AREA_GRAPHQL_ETC_PATH,
        self::AREA_WEBAPI_REST => self::AREA_WEBAPI_REST_ETC_PATH,
        self::AREA_WEBAPI_SOAP => self::AREA_WEBAPI_SOAP_ETC_PATH,
    ];

    protected TemplateRenderer $templateRenderer;

    public function __construct(
        TemplateRenderer $templateRenderer
    ){
        $this->templateRenderer = $templateRenderer;   
    }
    
    /**
     * @param \Zero1\MagentoDev\Model\module $module
     * @param string $event
     * @param string $area
     * @param null|string $name
     * @return boolean
     */
    public function hasObserver($module, $event, $area, $name = null)
    {
        if(!in_array($area, self::AREAS)){
            throw new \InvalidArgumentException('Area "'.$area.'" must be one of: '.implode(', ', self::AREAS));
        }

        $filepath = $module->getBaseDirectory().self::AREAS_ETC_MAP[$area];
        if(!is_file($filepath)){
            return false;
        }

        $events = new EventsModel($filepath);
        if(!$events->hasEvent($event)){
            return false;
        }
        if($name){
            $event = $events->getEvent($event);
            if($event->hasObserver($name)){
                return true;
            }
        }
        return false;
    }

    public function addObserver(
        $module,
        $eventName, 
        $area,
        $observerName,
        $instance,
        $failOnExisting = false
    ){
        if($this->hasObserver($module, $eventName, $area, $observerName) && $failOnExisting){
            throw new \InvalidArgumentException('Observer already exists');
        }

        $filepath = $module->getBaseDirectory().self::AREAS_ETC_MAP[$area];
        $events = new EventsModel(is_file($filepath)? $filepath: null);

        if(!$events->hasEvent($eventName)){
            $event = new Event();
            $event->name = $eventName;
            $events->addEvent($event);
        }else{
            $event = $events->getEvent($eventName);
        }

        if(!$event->hasObserver($observerName)){
            $observer = new Observer();
            $observer->name = $observerName;
            $event->addObserver($observer);
        }else{
            $observer = $event->getObserver($observerName);
        }
        $observer->instance = $instance;

        $renderer = $this->templateRenderer->getRenderer();
        file_put_contents($filepath, $events->toXml($renderer));
    }

    /**
     * @param string $filepath
     * @return DOMDocument
     */
    protected function loadDom($filepath)
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTMLFile($filepath, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        return $dom;
    }

    /**
     * @param string $filepath
     * @return DOMXPath
     */
    protected function loadXpath($filepath)
    {
        return new DOMXPath($this->loadDom($filepath));
    }
}