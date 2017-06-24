<?php

namespace PhpCommon\Annotation\Reader;

use PhpCommon\Annotation\AnnotationReader;
use Psr\Cache\CacheItemPoolInterface as CacheItemPool;
use Psr\Cache\CacheItemInterface as CacheItem;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionClass;

class CachedAnnotationReader implements AnnotationReader
{
    private $reader;
    private $cache;

    public function __construct(AnnotationReader $reader, CacheItemPool $cache)
    {
        $this->reader = $reader;
        $this->cache = $cache;
    }

    public function readFunctionAnnotations(ReflectionFunction $reflection, string $annotation = null) : array
    {
        $cacheItem = $this->getCacheItem($reflection);
        $annotations = $cacheItem->get();

        if ($annotations === null) {
            $annotations = $this->reader->readFunctionAnnotations($reflection, $annotation);
            $cacheItem->set($annotations);

            $this->cache->save($cacheItem);
        }

        return $annotations;
    }

    public function readMethodAnnotations(ReflectionMethod $reflection, string $annotation = null) : array
    {
        $cacheItem = $this->getCacheItem($reflection);
        $annotations = $cacheItem->get();

        if ($annotations === null) {
            $annotations = $this->reader->readMethodAnnotations($reflection, $annotation);
            $cacheItem->set($annotations);

            $this->cache->save($cacheItem);
        }

        return $annotations;
    }

    public function readClassAnnotations(ReflectionClass $reflection, string $annotation = null) : array
    {
        $cacheItem = $this->getCacheItem($reflection);
        $annotations = $cacheItem->get();

        if ($annotations === null) {
            $annotations = $this->reader->readClassAnnotations($reflection, $annotation);
            $cacheItem->set($annotations);

            $this->cache->save($cacheItem);
        }

        return $annotations;
    }

    private function getCacheItem($reflection) : CacheItem
    {
        return $this->cache->getItem($this->getCacheKey($reflection));
    }

    /**
     * @param $reflection ReflectionMethod|ReflectionFunction|ReflectionClass
     *
     * @return string
     */
    private function getCacheKey($reflection) : string
    {
        if ($reflection instanceof ReflectionMethod) {
            $class = $reflection->getDeclaringClass();
            $className = $class->getName();

            return sprintf('%s::%s', $className, $reflection->getName());
        }

        return $reflection->getName();
    }

}