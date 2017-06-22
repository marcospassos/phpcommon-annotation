<?php

namespace PhpCommon\Annotation;

use Throwable;

class AnnotationFactoryException extends AnnotationException
{
    private $name;

    public function __construct(string $message, string $name, Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);

        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
}