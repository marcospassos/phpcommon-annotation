<?php

namespace PhpCommon\Annotation;

use Doctrine\Common\Annotations\TokenParser;
use ReflectionFunctionAbstract;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionClass;

class AnnotationReader
{
    private $annotationFactory;
    private $blacklist;

    public function __construct(AnnotationFactory $annotationFactory, array $blacklist = [])
    {
        $this->annotationFactory = $annotationFactory;
        $this->blacklist = $blacklist;
    }

    public function readFunctionAnnotations(ReflectionFunction $reflection, string $annotation = null)
    {
        $annotations = $this->readCallableAnnotations($reflection, $annotation);

        return $annotations;
    }

    public function readMethodAnnotations(ReflectionMethod $reflection, string $annotation = null)
    {
        $annotations = $this->readCallableAnnotations($reflection, $annotation);

        return $annotations;
    }

    public function readClassAnnotations(ReflectionClass $reflection, string $annotation = null)
    {
        $filename = $reflection->getFileName();
        $namespace = $reflection->getNamespaceName();
        $docBlock = $reflection->getDocComment();
        $parser = $this->getParser($filename, $namespace);

        try {
            return $parser->parse($docBlock, $annotation);

        } catch (InvalidAnnotationException $exception) {
            $name = $exception->getName();

            throw new UnknownAnnotationException(
                sprintf(
                    'The class %s in %s is not an annotation type.',
                    $name,
                    $reflection->getName()
                ),
                $name,
                $exception
            );
        } catch (UnknownAnnotationException $exception) {
            $name = $exception->getName();

            throw new UnknownAnnotationException(
                sprintf(
                    'The annotation @%s found in %s cannot be loaded. Did you '.
                    'maybe forget to add a "use" statement for this ' .
                    'annotation?',
                    $name,
                    $reflection->getName()
                ),
                $name,
                $exception
            );
        } catch (MalformedAnnotationException $exception) {
            $index = $exception->getPosition();

            list($line, $column) = $this->findPosition($docBlock, $index);

            $line += $reflection->getStartLine();
            $line -= substr_count($docBlock, "\n") + 2;

            throw new MalformedAnnotationException(
                sprintf(
                    'Malformed annotation found in %s around line %d, '.
                    'column %d.',
                    $reflection->getName(),
                    $line,
                    $column
                ),
                $index,
                $exception
            );
        }
    }

    private function readCallableAnnotations(ReflectionFunctionAbstract $reflection, string $annotation = null)
    {
        $filename = $reflection->getFileName();
        $namespace = $reflection->getNamespaceName();
        $docBlock = $reflection->getDocComment();
        $parser = $this->getParser($filename, $namespace);

        try {
            return $parser->parse($docBlock, $annotation);
        } catch (InvalidAnnotationException $exception) {
            $name = $exception->getName();

            throw new UnknownAnnotationException(
                sprintf(
                    'The class %s in %s is not an annotation type.',
                    $name,
                    $filename
                ),
                $name,
                $exception
            );
        }catch (UnknownAnnotationException $exception) {
            $name = $exception->getName();

            throw new UnknownAnnotationException(
                sprintf(
                    'The annotation @%s found in %s cannot be loaded. Did you '.
                    'maybe forget to add a "use" statement for this ' .
                    'annotation?',
                    $name,
                    $filename
                ),
                $name,
                $exception
            );
        } catch (MalformedAnnotationException $exception) {
            $index = $exception->getPosition();

            list($line, $column) = $this->findPosition($docBlock, $index);

            $line += $reflection->getStartLine();
            $line -= substr_count($docBlock, "\n") + 2;

            throw new MalformedAnnotationException(
                sprintf(
                    'Malformed annotation found in %s around line %d, '.
                    'column %d.',
                    $filename,
                    $line,
                    $column
                ),
                $index,
                $exception
            );
        }
    }

    private function getParser($filename, $namespace)
    {
        $aliasesMap = $this->getNamespaceAliases($filename, $namespace);

        return new AnnotationParser(
            $this->annotationFactory,
            new NameResolver($aliasesMap),
            $this->blacklist
        );
    }

    private function getNamespaceAliases(string $filename, string $namespace)
    {
        $code = file_get_contents($filename);
        $tokenParser = new TokenParser($code);

        return $tokenParser->parseUseStatements($namespace);
    }

    private function findPosition(string $string, int $index) : array
    {
        $line = 1;
        $column = 0;

        for ($i = 0; $i < $index; $i++) {
            if ($string[$i] === "\n") {
                $line++;
                $column = 0;

                continue;
            }

            $column++;
        }

        return [$line, $column];
    }
}