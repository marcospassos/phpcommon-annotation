<?php

namespace PhpCommon\Annotation;

class NameResolver
{
    private $aliasesMap;

    public function __construct(array $aliasesMap)
    {
        $this->aliasesMap = $aliasesMap;
    }

    public function resolve(string $class) : string
    {
        foreach ($this->aliasesMap as $alias => $name) {
            if (stripos($class, $alias) === 0) {
                return $name . substr($class, strlen($alias));
            }
        }

        return $class;
    }

    public function getAliases(string $class) : array
    {
        $aliases = [$class];

        foreach ($this->aliasesMap as $alias => $name) {
            if (stripos($class, $name) === 0) {
                $aliases[] = $alias . substr($class, strlen($name));
            }
        }

        return $aliases;
    }
}