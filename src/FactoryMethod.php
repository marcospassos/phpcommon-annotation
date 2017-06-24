<?php

namespace PhpCommon\Annotation;

interface FactoryMethod
{
    public static function create(array $arguments);
}