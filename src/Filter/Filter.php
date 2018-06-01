<?php

declare(strict_types = 1);

/**
 * GnBit  (http://www.gnbit.com/)
 *
 * @author Benjamín Gonzales B. (benjamin@gnbit.com)
 * @Copyright (c) 2017 GnBit.SAC
 *
 */

namespace Alabra\Filter;

use Zend\InputFilter\InputFilter;

class Filter extends InputFilter
{

    /**
     * Display Errors Messages
     * @return array
     */
    public function getErrorsMessages(): array
    {
        $errors = [];
        foreach ($this->getInvalidInput() as $field => $error) {

            $errors[] = array_map(function($message) use ($field) {
                return [
                    'message' => $message,
                    'field'   => $field,
                    'value'   => $this->getRawValue($field),
                ];
            }, $error->getMessages());
        }

        return $errors;
    }

    /**
     * Returns filtered and validated data
     * @return array
     */
    public function getFilterData(): array
    {
        return array_merge($this->getValues(), $this->getUnknown());
    }

    /**
     * Remove Filters
     *
     * @param string $filters
     */
    public function removeFilters(string ...$filters)
    {
        foreach ($filters as $filter) {
            $this->remove($filter);
        }
    }

    /**
     * Apply only filters selecteds 
     *
     * @param string $filters
     */
    public function applyOnly(string ...$filters)
    {
        $inputs = $this->getInputs();
        foreach ($inputs as $k => $input) {
            if (!in_array($k, $filters)) {
                $this->remove($k);
            }
        }
    }
}
