<?php

namespace Aliziodev\WilayahBoundaries\Tests;

use Aliziodev\Wilayah\WilayahServiceProvider;
use Aliziodev\WilayahBoundaries\BoundariesServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            WilayahServiceProvider::class,
            BoundariesServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        // Support monorepo lokal dan layout vendor di CI/GitHub Actions.
        $coreMigrationPaths = [
            realpath(__DIR__.'/../../laravel-wilayah/src/Database/Migrations'),
            realpath(__DIR__.'/../vendor/aliziodev/laravel-wilayah/src/Database/Migrations'),
        ];

        foreach (array_filter($coreMigrationPaths) as $path) {
            $this->loadMigrationsFrom($path);
            break;
        }

        // Lalu migrasi boundaries
        $this->loadMigrationsFrom(__DIR__.'/../src/Database/Migrations');
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('wilayah.cache.enabled', false);
    }

    /**
     * Seed minimal: 1 provinsi + 1 boundary-nya
     */
    protected function seedTestData(): void
    {
        $now = now()->toDateTimeString();

        \DB::table('provinces')->insert([
            'code' => '11', 'name' => 'ACEH',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        \DB::table('regencies')->insert([
            'code' => '11.01', 'province_id' => 1, 'name' => 'KAB. SIMEULUE', 'type' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // Seed boundary provinsi dengan path JSON sederhana
        \DB::table('region_boundaries')->insert([
            'code' => '11',
            'level' => 1,
            'lat' => 4.2257,
            'lng' => 96.9118,
            'path' => '[[[[4.0,96.0],[5.0,96.0],[5.0,97.0],[4.0,97.0],[4.0,96.0]]]]',
            'status' => 1,
        ]);

        \DB::table('region_boundaries')->insert([
            'code' => '11.01',
            'level' => 2,
            'lat' => 2.5,
            'lng' => 96.0,
            'path' => '[[[[2.0,95.5],[3.0,95.5],[3.0,96.5],[2.0,96.5],[2.0,95.5]]]]',
            'status' => 1,
        ]);
    }
}
