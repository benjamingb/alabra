<?php
declare (strict_types = 1);

/**
 * GnBit  (http://www.gnbit.com/)
 *
 * @author Benjamín Gonzales B. (benjamin@gnbit.com)
 * @Copyright (c) 2017 GnBit.SAC
 *
 */

namespace Alabra\Entity;

use Zend\InputFilter\InputFilterInterface;

interface EntityInterface
{

    public function addFilter(InputFilterInterface $filter): InputFilterInterface;

    /**
     * Returns Entity in an array key->value
     * this method is used to persist the entitity
     * @return array
     */
    public function getArrayCopy();
}