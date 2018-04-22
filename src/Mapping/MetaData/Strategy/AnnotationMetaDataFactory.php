<?php declare(strict_types=1);

namespace Igni\Storage\Mapping\MetaData\Strategy;

use Cache\Adapter\PHPArray\ArrayCachePool;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\IndexedReader;
use Doctrine\Common\Annotations\Reader;
use Igni\Storage\Exception\MappingException;
use Igni\Storage\Mapping\Annotations\EmbeddedEntity;
use Igni\Storage\Mapping\Annotations\Entity;
use Igni\Storage\Mapping\Annotations;
use Igni\Storage\Mapping\MetaData\EntityMetaData;
use Igni\Storage\Mapping\MetaData\MetaDataFactory;
use Igni\Storage\Mapping\MetaData\PropertyMetaData;
use Igni\Storage\Mapping\Type;
use Igni\Utils\ReflectionApi;
use Psr\SimpleCache\CacheInterface;
use ReflectionProperty;

class AnnotationMetaDataFactory implements MetaDataFactory
{
    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var Reader
     */
    private $reader;

    public function __construct(CacheInterface $cache = null)
    {
        AnnotationRegistry::registerUniqueLoader('class_exists');
        $this->reader = new IndexedReader(new AnnotationReader());

        if ($cache === null) {
            $cache = new ArrayCachePool();
        }

        $this->cache = $cache;
    }

    public function getMetaData(string $entity): EntityMetaData
    {
        $cacheKey = str_replace('\\', '', $entity) . '.metadata';
        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $metaData = $this->parseMetaData($entity);
        $this->cache->set($cacheKey, $metaData);

        return $metaData;
    }

    protected function parseMetaData(string $entityClass): EntityMetaData
    {
        $metaData = new EntityMetaData($entityClass);
        $reflection = ReflectionApi::reflectClass($entityClass);

        $classAnnotations = $this->reader->getClassAnnotations($reflection);

        // Parse class annotations
        foreach ($classAnnotations as $type => $annotation) {
            switch ($type) {
                case Entity::class:
                    $source = $annotation->source ?? $annotation->value;
                    $metaData->setSource($source);
                    $this->setParentHydrator($annotation, $metaData);
                    break;
                case EmbeddedEntity::class:
                    $metaData->makeEmbed();
                    $this->setParentHydrator($annotation, $metaData);
                    break;
            }
        }

        // Parse property annotations
        foreach ($reflection->getProperties() as $property) {
            $annotations = $this->reader->getPropertyAnnotations($property);
            foreach ($annotations as $annotation) {
                if ($annotation instanceof Annotations\Type) {
                    $this->addProperty($property, $annotation, $metaData);
                    break;
                }
            }
        }

        return $metaData;
    }

    private function setParentHydrator(Annotation $annotation, EntityMetaData $metaData)
    {
        if ($annotation->hydrator !== null) {
            if (!class_exists($annotation->hydrator)) {
                throw new MappingException("Cannot use hydrator {$annotation->hydrator} class does not exist.");
            }

            $metaData->setParentHydratorClass($annotation->hydrator);
        }
    }

    private function addProperty(ReflectionProperty $property, Annotations\Type $annotation, EntityMetaData $metaData): void
    {
        if (!Type::has($annotation->getType())) {
            throw new MappingException("Cannot map property {$property->getDeclaringClass()->getName()}::{$property->getName()} - unknown type {$annotation->getType()}.");
        }

        $property = new PropertyMetaData(
            $property->getDeclaringClass()->getName(),
            $property->getName(),
            Type::get($annotation->getType())
        );
        $property->setFieldName($annotation->name ?? $property->getName());
        $property->setAttributes($annotation->getAttributes());
        $metaData->addProperty($property);
    }
}
