<?php declare(strict_types=1);

namespace Igni\Storage\Mapping\Annotations\Types;

use Igni\Storage\Id\Uuid;
use Igni\Storage\Mapping\Annotations\Type;

/**
 * @Annotation
 */
class Id extends Type
{
    public $class = Uuid::class;

    public function getType(): string
    {
        return 'id';
    }
}
