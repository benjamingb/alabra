<?php

namespace Alabra\Type;

class Nullable implements TypeInterfece
{

    public static function type()
    {
        return new self;
    }

    public function getValue()
    {
        return null;
    }
}
