<?php

namespace PhpCommon\Annotation;

interface Annotation
{
    public static function create(array $arguments) : Annotation;
}