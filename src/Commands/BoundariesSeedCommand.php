<?php

namespace Aliziodev\WilayahBoundaries\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BoundariesSeedCommand extends Command
{
    protected $signature = 'boundaries:seed
                            {--province= : Seed hanya untuk kode provinsi tertentu}
                            {--level= : Seed hanya level tertentu (1=prov,2=kab,3=kec,4=desa)}
                            {--fresh : Truncate tabel sebelum seed}';

    protected $description = 'Seed data batas wilayah (polygon) ke tabel region_boundaries.';

    public function handle(): int
    {
        $provinceFilter = $this->option('province');
        $levelFilter = $this->option('level');
        $fresh = $this->option('fresh');
        $dataDir = __DIR__.'/../../data';

        if ($fresh) {
            DB::table('region_boundaries')->truncate();
            $this->info('Tabel region_boundaries di-truncate.');
        }

        $levels = [
            1 => ['dir' => 'prov', 'label' => 'Provinsi'],
            2 => ['dir' => 'kab',  'label' => 'Kab/Kota'],
            3 => ['dir' => 'kec',  'label' => 'Kecamatan'],
            4 => ['dir' => 'kel',  'label' => 'Desa/Kelurahan'],
        ];

        foreach ($levels as $levelNum => $info) {
            if ($levelFilter && (int) $levelFilter !== $levelNum) {
                continue;
            }

            $dir = "{$dataDir}/{$info['dir']}";
            if (! is_dir($dir)) {
                continue;
            }

            $files = glob("{$dir}/boundaries_{$info['dir']}*.php");
            $this->comment("Seeding {$info['label']} (".count($files).' file)...');

            foreach ($files as $file) {
                if ($provinceFilter && ! str_contains($file, "_{$provinceFilter}")) {
                    continue;
                }

                $data = require $file;

                $rows = array_map(fn ($r) => [
                    'code' => $r['code'],
                    'level' => $levelNum,
                    'lat' => $r['lat'] ?? null,
                    'lng' => $r['lng'] ?? null,
                    'path' => is_array($r['path']) ? json_encode($r['path']) : $r['path'],
                    'status' => $r['status'] ?? 1,
                ], $data);

                foreach (array_chunk($rows, 100) as $batch) {
                    DB::table('region_boundaries')->upsert(
                        $batch,
                        ['code'],
                        ['lat', 'lng', 'path', 'status']
                    );
                }

                unset($data, $rows);
                gc_collect_cycles();
            }
        }

        $this->info('✅ Seeding boundaries selesai.');

        return self::SUCCESS;
    }
}
