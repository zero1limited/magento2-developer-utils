<?php

namespace Zero1\MagentoDev\Model\Magento;

use Zero1\MagentoDev\Model\Magento\AbstractObject;

/**
 * @property string value
 */
class Comment extends AbstractObject
{
    protected $attributes = [
        'value'
    ];

    /**
     * @param \DOMElement $node
     */
    public static function createFromDomNode($node)
    {
        $comment = new Comment();
        $comment->value = $node->nodeValue;
        return $comment;
    }
    
    // public function toXml(\Mustache_Engine $renderer)
    // {
    //     return $renderer->render(
    //         'etc/comment.xml',
    //         [
    //             'value' => $this->value,
    //         ]
    //     );
    // }
}