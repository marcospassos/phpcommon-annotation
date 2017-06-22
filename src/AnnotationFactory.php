<?php

namespace PhpCommon\Annotation;

class AnnotationFactory
{
    public function create(string $class, array $arguments)
    {
        if (!class_exists($class)) {
            throw new UnknownAnnotationException(
                sprintf(
                    'The annotation @%s cannot be loaded.',
                    $class
                ),
                $class
            );
        }

        if (!is_a($class, Annotation::class, true)) {
            throw new UnknownAnnotationException(
                sprintf(
                    'The class "%s" is not an annotation type.',
                    $class
                ),
                $class
            );
        }

        return call_user_func([$class, 'create'], $arguments);
    }
}