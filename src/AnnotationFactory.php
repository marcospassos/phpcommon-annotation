<?php

namespace PhpCommon\Annotation;

class AnnotationFactory
{
    public function create(string $name, array $members, Context $context)
    {
        $class = $context->resolveName($name);

        if (!class_exists($class)) {
            throw new AnnotationNotFoundException(sprintf(
                'The annotation @%s found in %s cannot be loaded. Did you '.
                'maybe forget to add a "use" statement for this ' .
                'annotation?',
                $name,
                $context->getSource()
            ));
        }

        if (is_a($class, FactoryMethod::class, true)) {
            return call_user_func([$class, 'create'], $members);
        }

        if (method_exists($class, '__construct')) {
            $reflection = new \ReflectionClass($class);
            $constructor = $reflection->getConstructor();
            $arguments = $this->resolveArguments($constructor, $members);

            return new $class(...$arguments);
        }

        $annotation = new $class();

        foreach ($members as $member => $value) {
            if (!property_exists($annotation, $member)) {
                throw new AnnotationFactoryException(sprintf(
                    'Property "%s" does not exist in %s.',
                    $member,
                    $class
                ));
            }

            $annotation->{$member} = $value;
        }

        return $annotation;
    }

    private function resolveArguments(\ReflectionMethod $reflection, array $members)
    {
        $arguments = [];
        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (isset($members[$name])) {
                $arguments[] = $members[$name];

                unset($members[$name]);

                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();

                continue;
            }

            throw new AnnotationFactoryException(sprintf(
                 'Argument "%s" is missing for constructor of %s.',
                 $name,
                 $reflection->getDeclaringClass()->getName()
             ));
        }

        if (!empty($members)) {
            throw new AnnotationFactoryException(sprintf(
                'Argument "%s" does not exist in the constructor of %s.',
                key($members),
                $reflection->getDeclaringClass()->getName()
            ));
        }

        return $arguments;
    }
}