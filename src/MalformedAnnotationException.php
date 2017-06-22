<?php

namespace PhpCommon\Annotation;

use Exception;

class MalformedAnnotationException extends AnnotationException
{
    private $position;

    public function __construct(string $message, int $position, Exception $previous = null)
    {
        parent::__construct($message, 0, $previous);

        $this->position = $position;
    }

    public static function atPosition(int $position) : MalformedAnnotationException
    {
        return new self(
            sprintf(
                'Malformed annotation at position %d.',
               $position
            ),
            $position
        );
    }

    /**
     * @return int
     */
    public function getPosition(): int
    {
        return $this->position;
    }
}