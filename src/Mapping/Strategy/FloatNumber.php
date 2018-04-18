<?php declare(strict_types=1);

namespace Igni\Storage\Mapping\Strategy;

use Igni\Storage\Mapping\MappingStrategy;

final class FloatNumber implements MappingStrategy
{
    public static function getHydrator(): string
    {
        return '
        $value = (float) $value;';
    }

    public static function getExtractor(): string
    {
        return '
        $value = (float) $value;';
    }
}
