<?php

namespace Aliziodev\WilayahBoundaries\Commands;

use Illuminate\Console\Command;

class BoundariesSyncCommand extends Command
{
    protected $signature = 'boundaries:sync
                              {--dry-run : Preview saja, tidak menerapkan perubahan}
                              {--province= : Sync hanya provinsi tertentu}';

    protected $description = 'Sinkronisasi data batas wilayah terbaru dari package (upsert).';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $province = $this->option('province');

        $versionFile = __DIR__.'/../../data/version.php';
        $pkg = file_exists($versionFile) ? require $versionFile : ['version' => 'N/A', 'data_date' => 'N/A'];

        $this->info('🗺  Boundaries Sync');
        $this->line("Package version : v{$pkg['version']} ({$pkg['data_date']})");

        $dbCount = \Illuminate\Support\Facades\DB::table('region_boundaries')->count();
        $pkgCount = $pkg['counts']['total'] ?? 'N/A';

        $this->line("Di database     : {$dbCount}");
        $this->line("Di package      : {$pkgCount}");

        if ($dryRun) {
            $this->comment('ℹ️  Dry-run: tidak ada perubahan diterapkan.');

            return self::SUCCESS;
        }

        $this->call('boundaries:seed', array_filter(['--province' => $province]));
        $this->info('✅ Sync selesai.');

        return self::SUCCESS;
    }
}
