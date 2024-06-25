<?php

namespace Zero1\MagentoDev\Model\Magento;

use DOMDocument;

abstract class AbstractObject implements \ArrayAccess, \Countable, \JsonSerializable
{
    protected $_values = [];

    protected $children = [];

    /**
     * @return array<string>
     */
    protected $attributes;

    #[\ReturnTypeWillChange]
    public function offsetSet($k, $v)
    {
        $this->{$k} = $v;
    }

    #[\ReturnTypeWillChange]
    public function offsetExists($k)
    {
        return \array_key_exists($k, $this->_values);
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($k)
    {
        unset($this->{$k});
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($k)
    {
        return \array_key_exists($k, $this->_values) ? $this->_values[$k] : null;
    }

    /**
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        return \count($this->_values);
    }

    public function keys()
    {
        return \array_keys($this->_values);
    }

    public function values()
    {
        return \_arrayvalues($this->_values);
    }

    /**
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * @return array<mixed>
     */
    public function toArray()
    {
        return $this->_values;
    }

    public function __set($k, $v)
    {
        if(!$this->isValidAttribute($k)){
            throw new \Exception('Unknown attribute: '.$k);
        }
        $this->_values[$k] = $v;
    }

    public function __get($k)
    {
        if(!$this->isValidAttribute($k)){
            throw new \Exception('Unknown attribute: '.$k);
        }
        if(!array_key_exists($k, $this->_values)){
            return null;
        }
        return $this->_values[$k];
    }

    protected function isValidAttribute($k)
    {
        return in_array($k, $this->attributes);
    }

    protected function loadDom($filepath)
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTMLFile($filepath, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        return $dom;
    }

    /**
     * @param \Zero1\MagentoDev\Model\Magento\AbstractObject $element
     * @return $this
     */
    public function addChild($element)
    {
        $this->children[] = $element;
        return $this;
    }

    /**
    * @param \Mustache_Engine $renderer
    * @return string
    */
    abstract public function toXml(\Mustache_Engine $renderer);
}