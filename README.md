# aliziodev/laravel-wilayah-boundaries

Addon data **polygon batas wilayah** Indonesia untuk package [`aliziodev/laravel-wilayah`](https://github.com/aliziodev/laravel-wilayah).

## Requirements

```bash
composer require aliziodev/laravel-wilayah-boundaries
```

> Membutuhkan `aliziodev/laravel-wilayah` sudah terinstall.

## Setup

```bash
php artisan vendor:publish --tag=wilayah-boundaries-migrations
php artisan migrate
php artisan boundaries:seed              # Seed semua level
php artisan boundaries:seed --province=32 # Hanya Jawa Barat
php artisan boundaries:seed --level=2    # Hanya level Kab/Kota
```

## Penggunaan

```php
use Aliziodev\WilayahBoundaries\Facades\Boundary;
use Aliziodev\Wilayah\Models\Province;

// 1. Menggunakan Facade
$geoJson = Boundary::forCode('32')->toGeoJson();
$centroid = Boundary::nearestTo(lat: -6.9175, lng: 107.6191, level: 4);
$collection = Boundary::collection(level: 1); // FeatureCollection semua provinsi

// 2. Menggunakan Relasi Eloquent (Auto-injected)
// ✅ BEST PRACTICE: Gunakan eager loading (with) untuk mencegah N+1 query
$provinces = Province::with('boundary')->get();

foreach ($provinces as $province) {
    $geoJson = $province->boundary?->toGeoJson();
    $centroid = $province->boundary?->centroid();
}
```

## Sync Data

```bash
php artisan boundaries:sync --dry-run
php artisan boundaries:sync
```

## License

MIT © [Aliziodev](https://github.com/aliziodev)
