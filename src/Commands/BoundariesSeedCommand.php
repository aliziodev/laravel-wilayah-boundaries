<?php

namespace Aliziodev\WilayahBoundaries\Commands;

use Aliziodev\WilayahBoundaries\Support\DatasetManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BoundariesSeedCommand extends Command
{
    protected $signature = 'boundaries:seed
                            {--province= : Seed hanya untuk kode provinsi tertentu}
                            {--level= : Seed hanya level tertentu (1=prov,2=kab,3=kec,4=desa)}
                            {--fresh : Truncate tabel sebelum seed}';

    protected $description = 'Seed data batas wilayah (polygon) ke tabel region_boundaries.';

    public function __construct(private readonly DatasetManager $datasetManager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $provinceFilter = $this->option('province');
        $levelFilter = $this->option('level');
        $fresh = $this->option('fresh');
        $datasetPath = $this->datasetManager->ensureDatasetAvailable();

        if ($fresh) {
            DB::table('region_boundaries')->truncate();
            $this->info('Tabel region_boundaries di-truncate.');
        }

        $labels = [
            1 => 'Provinsi',
            2 => 'Kab/Kota',
            3 => 'Kecamatan',
            4 => 'Desa/Kelurahan',
        ];

        $this->comment("Dataset: {$datasetPath}");

        $inserted = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        $buffers = [1 => [], 2 => [], 3 => [], 4 => []];

        $handle = gzopen($datasetPath, 'rb');
        if (! $handle) {
            $this->error('Gagal membuka boundary dataset terkompresi.');

            return self::FAILURE;
        }

        while (! gzeof($handle)) {
            $line = gzgets($handle);
            if (! is_string($line)) {
                continue;
            }

            $record = json_decode(trim($line), true);
            if (! is_array($record)) {
                continue;
            }

            $levelNum = (int) ($record['level'] ?? 0);
            if ($levelNum < 1 || $levelNum > 4) {
                continue;
            }

            if ($levelFilter && (int) $levelFilter !== $levelNum) {
                continue;
            }

            if ($provinceFilter && substr((string) $record['code'], 0, 2) !== $provinceFilter) {
                continue;
            }

            $buffers[$levelNum][] = [
                'code' => $record['code'],
                'level' => $levelNum,
                'lat' => $record['lat'] ?? null,
                'lng' => $record['lng'] ?? null,
                'path' => is_array($record['path']) ? json_encode($record['path']) : $record['path'],
                'status' => $record['status'] ?? 1,
            ];

            if (count($buffers[$levelNum]) >= 100) {
                $this->flushBatch($buffers[$levelNum]);
                $inserted[$levelNum] += count($buffers[$levelNum]);
                $buffers[$levelNum] = [];
            }
        }

        gzclose($handle);

        foreach ($buffers as $levelNum => $batch) {
            if ($batch === []) {
                continue;
            }

            $this->flushBatch($batch);
            $inserted[$levelNum] += count($batch);
        }

        foreach ($inserted as $levelNum => $count) {
            if ($levelFilter && (int) $levelFilter !== $levelNum) {
                continue;
            }

            $this->comment("Seeding {$labels[$levelNum]} ({$count} row)...");
        }

        $this->info('✅ Seeding boundaries selesai.');

        return self::SUCCESS;
    }

    private function flushBatch(array $batch): void
    {
        DB::table('region_boundaries')->upsert(
            $batch,
            ['code'],
            ['lat', 'lng', 'path', 'status']
        );
    }
}
