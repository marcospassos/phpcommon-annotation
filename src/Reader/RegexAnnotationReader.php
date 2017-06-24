<?php

namespace PhpCommon\Annotation\Reader;

use Doctrine\Common\Annotations\TokenParser;
use PhpCommon\Annotation\AnnotationFactory;
use PhpCommon\Annotation\AnnotationReader;
use PhpCommon\Annotation\Context;
use PhpCommon\Annotation\RegexAnnotationParser;
use ReflectionFunctionAbstract;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionClass;

class RegexAnnotationReader implements AnnotationReader
{
    private $factory;
    private $ignoreList;

    public function __construct(AnnotationFactory $factory, array $ignoreList = [])
    {
        $this->factory = $factory;
        $this->ignoreList = $ignoreList;
    }

    public function readFunctionAnnotations(ReflectionFunction $reflection, string $annotation = null) : array
    {
        return $this->readAnnotations($reflection, $annotation);
    }

    public function readMethodAnnotations(ReflectionMethod $reflection, string $annotation = null) : array
    {
        return $this->readAnnotations($reflection, $annotation);
    }

    public function readClassAnnotations(ReflectionClass $reflection, string $annotation = null) : array
    {
        return $this->readAnnotations($reflection, $annotation);
    }

    /**
     * @param ReflectionFunctionAbstract|ReflectionClass $reflection
     * @param string|null $annotation
     *
     * @return array
     */
    private function readAnnotations($reflection, string $annotation = null)
    {
        $namespace = $reflection->getNamespaceName();
        $docBlock = $reflection->getDocComment();
        $filename = $reflection->getFileName();
        $line = $reflection->getStartLine() - substr_count($docBlock, "\n") - 1;
        $aliases = $this->getNamespaceAliases($filename, $namespace);
        $source = $this->getSource($reflection);

        $context = new Context($aliases, $this->ignoreList, $source, $line);
        $parser = $this->getParser($context);

        return $parser->parse($docBlock, $annotation);
    }

    /**
     * @param $reflection ReflectionMethod|ReflectionFunction|ReflectionClass
     *
     * @return string
     */
    private function getSource($reflection)
    {
        if ($reflection instanceof ReflectionFunction) {
            return sprintf('%s()', $reflection->getName());
        }

        if ($reflection instanceof ReflectionMethod) {
            $class = $reflection->getDeclaringClass();
            $className = $class->getName();

            return sprintf('%s::%s()', $className, $reflection->getName());
        }

        return $reflection->getName();
    }

    private function getParser(Context $context)
    {
        return new RegexAnnotationParser($this->factory, $context);
    }

    private function getNamespaceAliases(string $filename, string $namespace)
    {
        $code = file_get_contents($filename);
        $tokenParser = new TokenParser($code);

        return $tokenParser->parseUseStatements($namespace);
    }
}