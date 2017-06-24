<?php

namespace PhpCommon\Annotation;

class Context
{
    private $aliasesMap;
    private $ignoreList;
    private $source;
    private $line;
    private $column;

    public function __construct(array $aliasesMap = [], array $ignoreList = [], string $source = null, int $line = 0, int $column = 0)
    {
        $this->aliasesMap = $aliasesMap;
        $this->ignoreList = array_map('strtolower', $ignoreList);
        $this->source = $source;
        $this->line = $line;
        $this->column = $column;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * @return int
     */
    public function getColumn(): int
    {
        return $this->column;
    }

    public function getSource(): string
    {
        return $this->source ?: 'unknown source';
    }

    /**
     * @return array
     */
    public function getAliasesMap(): array
    {
        return $this->aliasesMap;
    }

    /**
     * @return array
     */
    public function getIgnoreList(): array
    {
        return $this->ignoreList;
    }

    public function shouldIgnore(string $name) : bool
    {
        return in_array(strtolower($name), $this->ignoreList, true);
    }

    public function resolveName(string $class) : string
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