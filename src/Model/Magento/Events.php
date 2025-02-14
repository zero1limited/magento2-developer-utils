<?php

namespace Zero1\MagentoDev\Model\Magento;

use Zero1\MagentoDev\Model\Magento\AbstractObject;
use Zero1\MagentoDev\Model\Magento\Events\Event;

/**
 */
class Events extends AbstractObject
{
    protected $attributes = [];

    protected $events = [];

    /**
     * @param \DOMElement $node
     */
    public function __construct($filepath = null)
    {
        if($filepath){
            $dom = $this->loadDom($filepath);
            foreach($dom->childNodes as $node){
                if($node->nodeName == 'config'){
                    /** @var \DOMElement $childNode */
                    foreach($node->childNodes as $childNode){
                        if($childNode->nodeType == XML_COMMENT_NODE){
                            $this->addChild(Comment::createFromDomNode($childNode));
                        }
                        if($childNode->nodeName == 'event'){
                            $this->addEvent(Event::createFromDomNode($childNode));
                        }
                    }
                }
            }
        }
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasEvent($name)
    {
        return isset($this->events[$name]);
    }

    /**
     * @param string $name
     * @return $this
     */
    public function removeEvent($name)
    {
        if($this->hasEvent($name)){
            unset($this->events[$name]);

            foreach($this->children as $x => $child){
                if($child instanceof \Zero1\MagentoDev\Model\Magento\Events\Event && $child->name == $name){
                    unset($this->children[$x]);
                    break;
                }
            }
        }
        return $this;
    }

    /**
     * @param \Zero1\MagentoDev\Model\Magento\Events\Event $event
     * @return $this
     */
    public function addEvent($event)
    {
        $this->addChild($event);
        $this->events[$event->name] = $event;
        return $this;
    }

    /**
     * @param string $name
     * @param \Zero1\MagentoDev\Model\Magento\Events\Event $event
     * @return $this
     */
    public function replaceEvent($name, $event)
    {
        return $this->removeEvent($name)
            ->addEvent($event);
    }

    /**
     * @param string $name
     * @return \Zero1\MagentoDev\Model\Magento\Events\Event
     */
    public function getEvent($name)
    {
        if(!$this->hasEvent($name)){
            throw new \InvalidArgumentException('Event "'.$name.'" not found');
        }
        return $this->events[$name];
    }
    
    /**
     * @param \Mustache_Engine $renderer
     * @return string
     */
    public function toXml(\Mustache_Engine $renderer)
    {
        $children = [];
        foreach($this->children as $child){
            $children[] = $child->toXml($renderer);
        }
        return $renderer->render(
            'etc/events.xml',
            [
                'children' => $children,
            ]
        );
    }
}