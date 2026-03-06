<?php

namespace Aliziodev\WilayahBoundaries\Models;

use Illuminate\Database\Eloquent\Model;

class Boundary extends Model
{
    public $timestamps = false;

    protected $table = 'region_boundaries';

    protected $fillable = [
        'code', 'level', 'lat', 'lng', 'path', 'status',
    ];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'status' => 'integer',
        'level' => 'integer',
    ];

    /**
     * Konversi path ke format GeoJSON Feature.
     *
     * Upstream menyimpan koordinat dalam format MultiPolygon [[[[lat,lng],...]]].
     * Catatan: upstream menggunakan urutan [lat, lng] — kita konversi ke [lng, lat]
     * agar sesuai standar GeoJSON RFC 7946.
     */
    public function toGeoJson(): array
    {
        $rawPath = $this->path;

        // path berupa JSON string
        if (is_string($rawPath)) {
            $decoded = json_decode($rawPath, true);
            $coords = $decoded ?? [];
        } else {
            $coords = $rawPath ?? [];
        }

        // Deteksi apakah MultiPolygon (4 level) atau Polygon (3 level)
        $isMultiPolygon = isset($coords[0][0][0][0]) && is_array($coords[0][0][0][0]);

        // Konversi dari [lat, lng] → [lng, lat] sesuai standar GeoJSON
        $convert = function (array $rings) use (&$convert): array {
            if (is_numeric($rings[0])) {
                // Titik koordinat: [lat, lng] → [lng, lat]
                return [$rings[1], $rings[0]];
            }

            return array_map($convert, $rings);
        };

        if ($isMultiPolygon) {
            $type = 'MultiPolygon';
            $coordinates = $convert($coords);
        } else {
            $type = 'Polygon';
            $coordinates = $convert($coords);
        }

        return [
            'type' => 'Feature',
            'geometry' => [
                'type' => $type,
                'coordinates' => $coordinates,
            ],
            'properties' => [
                'code' => $this->code,
                'name' => $this->name ?? null,
                'level' => $this->level,
                'lat' => (float) $this->lat,
                'lng' => (float) $this->lng,
            ],
        ];
    }

    /**
     * Centroid wilayah sebagai [lat, lng].
     */
    public function centroid(): array
    {
        return [(float) $this->lat, (float) $this->lng];
    }
}
