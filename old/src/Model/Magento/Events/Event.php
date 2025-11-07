<?php

namespace Zero1\MagentoDev\Model\Magento\Events;

use Zero1\MagentoDev\Model\Magento\AbstractObject;
use Zero1\MagentoDev\Model\Magento\Comment;
use Zero1\MagentoDev\Model\Magento\Events\Event\Observer;

/**
 * @property string $name
 */
class Event extends AbstractObject
{
    protected $attributes = [
        'name',
    ];

    protected $observers = [];

    /**
     * @param \DOMElement $node
     * @return \Zero1\MagentoDev\Model\Magento\Events\Event
     */
    public static function createFromDomNode($node)
    {
        $event = new Event();
        $event->name = $node->getAttribute('name');
        /** @var \DOMElement $childNode */
        foreach($node->childNodes as $childNode){
            if($childNode->nodeType == XML_COMMENT_NODE){
                $event->addChild(Comment::createFromDomNode($childNode));
            }
            if($childNode->nodeName == 'observer'){
                $event->addObserver(Observer::createFromDomNode($childNode));
            }
        }
        return $event;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasObserver($name)
    {
        return isset($this->observers[$name]);
    }

    /**
     * @param string $name
     * @return $this
     */
    public function removeObserver($name)
    {
        if($this->hasObserver($name)){
            unset($this->observers[$name]);

            foreach($this->children as $x => $child){
                if($child instanceof \Zero1\MagentoDev\Model\Magento\Events\Event\Observer && $child->name == $name){
                    unset($this->children[$x]);
                    break;
                }
            }
        }
        return $this;
    }

    /**
     * @param \Zero1\MagentoDev\Model\Magento\Events\Event\Observer $observer
     * @return $this
     */
    public function addObserver($observer)
    {
        $this->addChild($observer);
        $this->observers[$observer->name] = $observer;
        return $this;
    }

    public function getObserver($name)
    {
        if(!$this->hasObserver($name)){
            throw new \InvalidArgumentException('Unable to find observer "'.$name.'"');
        }
        return $this->observers[$name];
    }

    /**
     * @param string $name
     * @param \Zero1\MagentoDev\Model\Magento\Events\Event\Observer $observer
     * @return $this
     */
    public function replaceObserver($name, $observer)
    {
        return $this->removeObserver($name)
            ->addObserver($observer);
    }
    
    // public function toXml(\Mustache_Engine $renderer)
    // {
    //     $children = [];
    //     /** @var \Zero1\MagentoDev\Model\Magento\AbsractObject $child */
    //     foreach($this->children as $child){
    //         $children[] = $child->toXml($renderer);
    //     }
    //     return $renderer->render(
    //         'etc/event.xml',
    //         [
    //             'name' => $this->name,
    //             'children' => $children,
    //         ]
    //     );
    // }
}