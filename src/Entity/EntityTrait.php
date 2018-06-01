<?php

declare(strict_types = 1);

/**
 * GnBit  (http://www.gnbit.com/)
 *
 * @author Benjamín Gonzales B. (benjamin@gnbit.com)
 * @Copyright (c) 2017 GnBit.SAC
 *
 */

namespace Alabra\Entity;

use Alabra\Exception;
use Zend\InputFilter\InputFilterInterface;

trait EntityTrait
{

    /**
     * List of attributes names that should not be included in JSON or Array
     * representations of this Entity
     * @var array
     */
    protected $hiddenAttrs = [
        'filter',
        'attrs',
        'hiddenAttrs',
    ];

    /**
     * filter class
     * @var InputFilterInterface
     */
    protected $filter;

    /**
     * List of attribs passed in the constructor
     * @var array
     */
    protected $attrs = [];

    /**
     * Get value the $property
     *
     * @param string $property
     * @return $property
     * @throws Exception\InvalidArgumentException
     */
    public function __get($property)
    {
        if (property_exists(__CLASS__, $property)) {
            return $this->{$property};
        } else {
            throw new Exception\InvalidArgumentException('Undefined property: ' . __CLASS__ . "::\$$property");
        }
    }

    /**
     * Get Hidden Attribs
     *
     * @return array
     */
    protected function getHiddenAttrs(): array
    {
        return array_merge($this->hiddenAttrs);
    }

    /**
     * Adding Filter
     *
     * @param InputFilterInterface $filter
     * @return \self
     */
    public function addFilter(InputFilterInterface $filter): InputFilterInterface
    {
        return $this->filter = $filter->setData($this->getArrayCopy());
    }

    /**
     * Specify the filters to apply
     *
     * @param string $filters
     * @throws \Exception
     */
    public function removeFilters(string ...$filters)
    {
        if (!$this->filter) {
            throw new \Exception('The filters are not defined in : ' . __CLASS__);
        }

        foreach ($filters as $filter) {
            $this->filter->remove($filter);
        }
    }

    /**
     * Validate And filter
     *
     * @return bool
     * @throws \Exception
     */
    public function doValidate()
    {
        if (!$this->filter) {
            throw new \Exception('The filters are not defined in : ' . __CLASS__);
        }
        if ($this->filter->isValid()) {
            foreach ($this->filter->getValues() as $key => $val) {
                //only value filtered and/or validated
                !property_exists($this, $key) ?: $this->{$key} = $val;
            }
            return true;
        }
        return false;
    }

    /**
     * Get Filter
     *
     * @return null|InputFilterInterface
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * Returns Entity in json format
     * @return json string
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }

    /**
     * Returns Entity in an array key->value
     * @return array
     */
    public function toArray(): array
    {
        return array_filter(get_object_vars($this), function($key) {
            if (!in_array($key, $this->hiddenAttrs)) {
                return $key;
            }
        }, ARRAY_FILTER_USE_KEY);
    }
}
