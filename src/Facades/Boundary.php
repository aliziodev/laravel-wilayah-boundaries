<?php

namespace Aliziodev\WilayahBoundaries\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Aliziodev\WilayahBoundaries\Models\Boundary|null forCode(string $code)
 * @method static \Illuminate\Database\Eloquent\Builder forLevel(int $level)
 * @method static \Illuminate\Database\Eloquent\Builder forChildrenOf(string $parentCode)
 * @method static array centroids(int $level)
 * @method static array collection(int $level, string|null $parentCode = null)
 * @method static \Aliziodev\WilayahBoundaries\Models\Boundary|null nearestTo(float $lat, float $lng, int $level = 4)
 *
 * @see \Aliziodev\WilayahBoundaries\Services\BoundaryService
 */
class Boundary extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'wilayah-boundary';
    }
}
