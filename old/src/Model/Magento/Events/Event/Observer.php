<?php

namespace Zero1\MagentoDev\Model\Magento\Events\Event;

use Zero1\MagentoDev\Model\Magento\AbstractObject;

/**
 * @property string $name
 * @property string $instance
 */
class Observer extends AbstractObject
{
    protected $attributes = [
        'name',
        'instance',
    ];

    /**
     * @param \DOMElement $node
     * @return \Zero1\MagentoDev\Model\Magento\Events\Event
     */
    public static function createFromDomNode($node)
    {
        $observer = new Observer();
        $observer->name = $node->getAttribute('name');
        $observer->instance = $node->getAttribute('instance');
        return $observer;
    }
    
    // public function toXml(\Mustache_Engine $renderer)
    // {
    //     return $renderer->render(
    //         'etc/observer.xml',
    //         [
    //             'name' => $this->name,
    //             'instance' => $this->instance,
    //         ]
    //     );
    // }
}