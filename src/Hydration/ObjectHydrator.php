<?php declare(strict_types=1);

namespace Igni\Storage\Hydration;

interface ObjectHydrator
{
    public function hydrate(array $data);
    public function extract($entity): array;
}
