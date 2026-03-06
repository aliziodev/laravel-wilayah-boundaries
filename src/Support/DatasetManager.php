<?php

namespace Aliziodev\WilayahBoundaries\Support;

use RuntimeException;

class DatasetManager
{
    public function manifest(): array
    {
        $manifestPath = $this->manifestPath();

        if (! file_exists($manifestPath)) {
            return [];
        }

        $manifest = require $manifestPath;

        return is_array($manifest) ? $manifest : [];
    }

    public function manifestPath(): string
    {
        return __DIR__.'/../../data/version.php';
    }

    public function datasetPath(?array $manifest = null): string
    {
        $manifest ??= $this->manifest();

        $storagePath = rtrim((string) config('boundaries.dataset.storage_path', storage_path('app/wilayah-boundaries')), DIRECTORY_SEPARATOR);
        $assetName = $manifest['asset']['name'] ?? 'boundaries-dataset.ndjson.gz';

        return $storagePath.DIRECTORY_SEPARATOR.$assetName;
    }

    public function ensureDatasetAvailable(): string
    {
        $manifest = $this->manifest();
        $datasetPath = $this->datasetPath($manifest);
        $localBuildPath = __DIR__.'/../../.build/boundaries-dataset.ndjson.gz';

        if (file_exists($datasetPath) && filesize($datasetPath) > 0) {
            return $datasetPath;
        }

        if (file_exists($localBuildPath) && filesize($localBuildPath) > 0) {
            return $localBuildPath;
        }

        $assetUrl = $manifest['asset']['url'] ?? null;

        if (! is_string($assetUrl) || $assetUrl === '') {
            throw new RuntimeException('Boundary dataset asset URL tidak tersedia di manifest package.');
        }

        $directory = dirname($datasetPath);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Gagal membuat direktori dataset: {$directory}");
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: aliziodev-boundaries-dataset/1.0\r\n",
                'timeout' => (int) config('boundaries.dataset.asset_timeout', 300),
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $stream = @fopen($assetUrl, 'rb', false, $context);
        if (! $stream) {
            throw new RuntimeException("Gagal mengunduh boundary dataset dari {$assetUrl}");
        }

        $target = @fopen($datasetPath, 'wb');
        if (! $target) {
            fclose($stream);
            throw new RuntimeException("Gagal menulis boundary dataset ke {$datasetPath}");
        }

        stream_copy_to_stream($stream, $target);
        fclose($stream);
        fclose($target);

        if (! file_exists($datasetPath) || filesize($datasetPath) === 0) {
            throw new RuntimeException('Boundary dataset terunduh tetapi kosong.');
        }

        return $datasetPath;
    }
}
