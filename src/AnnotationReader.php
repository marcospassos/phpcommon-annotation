<?php

namespace PhpCommon\Annotation;

use ReflectionFunction;
use ReflectionMethod;
use ReflectionClass;

interface AnnotationReader
{
    public function readFunctionAnnotations(ReflectionFunction $reflection, string $annotation = null) : array;
    public function readMethodAnnotations(ReflectionMethod $reflection, string $annotation = null) : array;
    public function readClassAnnotations(ReflectionClass $reflection, string $annotation = null) : array;
}