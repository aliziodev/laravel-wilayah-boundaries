<?php

namespace Aliziodev\WilayahBoundaries;

use Aliziodev\WilayahBoundaries\Commands\BoundariesSeedCommand;
use Aliziodev\WilayahBoundaries\Commands\BoundariesSyncCommand;
use Aliziodev\WilayahBoundaries\Services\BoundaryService;
use Aliziodev\WilayahBoundaries\Support\DatasetManager;
use Illuminate\Support\ServiceProvider;

class BoundariesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/boundaries.php', 'boundaries');

        // Daftarkan BoundaryService sebagai singleton
        $this->app->singleton('wilayah-boundary', fn () => new BoundaryService);
        $this->app->alias('wilayah-boundary', BoundaryService::class);
        $this->app->singleton(DatasetManager::class, fn () => new DatasetManager);
    }

    public function boot(): void
    {
        // Inject relasi 'boundary' ke 4 model utama secara dinamis
        $this->injectRelations();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/boundaries.php' => config_path('boundaries.php'),
            ], 'wilayah-boundaries-config');

            $this->publishes([
                __DIR__.'/Database/Migrations/' => database_path('migrations'),
            ], 'wilayah-boundaries-migrations');

            $this->commands([
                BoundariesSeedCommand::class,
                BoundariesSyncCommand::class,
            ]);
        }
    }

    protected function injectRelations(): void
    {
        $models = [
            config('wilayah.models.province'),
            config('wilayah.models.regency'),
            config('wilayah.models.district'),
            config('wilayah.models.village'),
        ];

        foreach ($models as $model) {
            if ($model && class_exists($model)) {
                $model::resolveRelationUsing('boundary', function ($instance) {
                    return $instance->hasOne(
                        \Aliziodev\WilayahBoundaries\Models\Boundary::class,
                        'code',
                        'code'
                    );
                });
            }
        }
    }
}
