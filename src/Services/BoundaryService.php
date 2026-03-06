<?php

namespace Aliziodev\WilayahBoundaries\Services;

use Aliziodev\WilayahBoundaries\Models\Boundary;
use Illuminate\Database\Eloquent\Builder;

class BoundaryService
{
    /**
     * Dapatkan batas wilayah berdasarkan kode.
     */
    public function forCode(string $code): ?Boundary
    {
        return Boundary::where('code', $code)->first();
    }

    /**
     * Dapatkan semua batas untuk level tertentu.
     *
     * @param  int  $level  1=provinsi, 2=kab/kota, 3=kecamatan, 4=desa
     */
    public function forLevel(int $level): Builder
    {
        return Boundary::where('level', $level);
    }

    /**
     * Dapatkan semua batas di dalam suatu batas induk (berdasarkan prefix kode).
     *
     * Contoh: forChildrenOf('32') → semua batas kab/kota di Jawa Barat
     */
    public function forChildrenOf(string $parentCode): Builder
    {
        return Boundary::where('code', 'LIKE', $parentCode.'%')
            ->where('code', '!=', $parentCode);
    }

    /**
     * Dapatkan centroid semua wilayah level tertentu sebagai array [{code, lat, lng}].
     */
    public function centroids(int $level): array
    {
        return Boundary::where('level', $level)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->get(['code', 'lat', 'lng'])
            ->map(fn ($b) => [
                'code' => $b->code,
                'lat' => (float) $b->lat,
                'lng' => (float) $b->lng,
            ])
            ->toArray();
    }

    /**
     * Export GeoJSON FeatureCollection untuk suatu level.
     */
    public function toGeoJsonCollection(int $level, ?string $parentCode = null): array
    {
        $query = Boundary::where('level', $level);

        if ($parentCode) {
            $query->where('code', 'LIKE', $parentCode.'%');
        }

        $features = $query->get()->map(fn ($b) => $b->toGeoJson())->toArray();

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }

    /**
     * Cari batas berdasarkan titik koordinat (point-in-polygon lookup).
     * Hanya returns batas yang centroid-nya paling dekat dengan titik tersebut.
     *
     * Untuk polygon lookup yang akurat, gunakan spatial extension database.
     */
    public function nearestTo(float $lat, float $lng, int $level = 4): ?Boundary
    {
        // Haversine formula menggunakan DB raw (MySQL / PostgreSQL)
        return Boundary::where('level', $level)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->selectRaw(
                '*, (6371 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) AS distance',
                [$lat, $lng, $lat]
            )
            ->orderBy('distance')
            ->first();
    }
}
