<?php

uses(\Aliziodev\WilayahBoundaries\Tests\TestCase::class);
use Aliziodev\Wilayah\Models\Province;
use Aliziodev\Wilayah\Models\Regency;
use Aliziodev\WilayahBoundaries\Models\Boundary;
use Aliziodev\WilayahBoundaries\Services\BoundaryService;

beforeEach(function () {
    $this->seedTestData();
});

test('boundary dapat diakses dari model province', function () {
    $province = Province::where('code', '11')->first();

    expect($province->boundary)->not->toBeNull();
    expect($province->boundary->code)->toEqual('11');
    expect($province->boundary->level)->toEqual(1);
});

test('boundary dapat diakses dari model regency', function () {
    $regency = Regency::where('code', '11.01')->first();

    expect($regency->boundary)->not->toBeNull();
    expect($regency->boundary->code)->toEqual('11.01');
    expect($regency->boundary->level)->toEqual(2);
});

test('wilayah tanpa boundary mengembalikan null', function () {
    // Province 73 tidak ada di seed
    $province = Province::where('code', '73')->first();
    expect($province)->toBeNull();
});

test('to geo json mengembalikan format geojson valid', function () {
    $boundary = Boundary::where('code', '11')->first();
    $geoJson = $boundary->toGeoJson();

    expect($geoJson)->toBeArray();
    expect($geoJson['type'])->toEqual('Feature');
    expect($geoJson)->toHaveKey('geometry');
    expect($geoJson)->toHaveKey('properties');
    expect($geoJson['geometry']['type'] ?? 'MultiPolygon')->toEqual('Polygon');
});

test('centroid mengembalikan koordinat', function () {
    $boundary = Boundary::where('code', '11')->first();

    expect($boundary->lat)->toEqual(4.2257);
    expect($boundary->lng)->toEqual(96.9118);
});

test('service for code mengembalikan boundary yang benar', function () {
    $service = new BoundaryService;
    $boundary = $service->forCode('11');

    expect($boundary)->not->toBeNull();
    expect($boundary->code)->toEqual('11');
});

test('service for code mengembalikan null jika tidak ada', function () {
    $service = new BoundaryService;
    $boundary = $service->forCode('99');

    expect($boundary)->toBeNull();
});

test('service for level mengembalikan semua boundary level', function () {
    $service = new BoundaryService;
    $boundaries = $service->forLevel(1)->get();

    expect($boundaries)->toHaveCount(1);
    expect($boundaries->first()->code)->toEqual('11');
});

test('service for children of mengembalikan boundary anak', function () {
    $service = new BoundaryService;
    $children = $service->forChildrenOf('11')->get();

    // 11.01 adalah anak dari 11
    expect($children)->toHaveCount(1);
    expect($children->first()->code)->toEqual('11.01');
});

test('service centroids mengembalikan array koordinat', function () {
    $service = new BoundaryService;
    $centroids = $service->centroids(1);

    expect($centroids)->toBeArray();
    expect($centroids)->toHaveCount(1);
    expect($centroids[0])->toHaveKey('code');
    expect($centroids[0])->toHaveKey('lat');
    expect($centroids[0])->toHaveKey('lng');
});

test('service to geo json collection mengembalikan feature collection', function () {
    $service = new BoundaryService;
    $collection = $service->toGeoJsonCollection(level: 1);

    expect($collection['type'])->toEqual('FeatureCollection');
    expect($collection['features'])->toBeArray();
    expect($collection['features'])->toHaveCount(1);
});
