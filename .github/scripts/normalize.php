<?php

const UPSTREAM_BASE = 'https://raw.githubusercontent.com/cahyadsn/wilayah_boundaries/refs/heads/main/db';
const BUILD_DIR = __DIR__.'/../../.build';
const DATASET_PATH = BUILD_DIR.'/boundaries-dataset.ndjson.gz';
const MANIFEST_PATH = __DIR__.'/../../data/version.php';

$LEVELS = [
    'prov' => ['prefix' => 'wilayah_boundaries_prov_', 'count' => 8, 'level_id' => 1],
    'kab' => ['prefix' => 'wilayah_boundaries_kab_', 'level_id' => 2],
    'kec' => ['prefix' => 'wilayah_boundaries_kec_', 'level_id' => 3],
    'kel' => ['prefix' => 'wilayah_boundaries_kel_', 'level_id' => 4],
];

$PROVINCE_CODES = [
    '11', '12', '13', '14', '15', '16', '17', '18', '19',
    '21',
    '31', '32', '33', '34', '35', '36',
    '51', '52', '53',
    '61', '62', '63', '64', '65',
    '71', '72', '73', '74', '75', '76',
    '81', '82',
    '91', '92', '93', '94', '95', '96',
];

$inputBase = $argv[1] ?? UPSTREAM_BASE;
$isUrl = str_starts_with($inputBase, 'http');

$totalRecords = 0;
$countsByLevel = ['prov' => 0, 'kab' => 0, 'kec' => 0, 'kel' => 0];
$regencyCodesByProvince = [];

echo "=== Normalizer Wilayah Boundaries ===\n";
echo "Input   : {$inputBase}\n";
echo "Dataset : ".DATASET_PATH."\n";
echo "Manifest: ".MANIFEST_PATH."\n\n";

@mkdir(BUILD_DIR, 0755, true);
@mkdir(dirname(MANIFEST_PATH), 0755, true);

$datasetHandle = gzopen(DATASET_PATH, 'wb9');
if (! $datasetHandle) {
    fwrite(STDERR, "ERROR: Gagal membuat dataset gzip di ".DATASET_PATH."\n");
    exit(1);
}

function openStream(string $path): mixed
{
    if (str_starts_with($path, 'http')) {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: aliziodev-boundaries-normalizer/1.0\r\n",
                'timeout' => 120,
            ],
        ]);

        $handle = @fopen($path, 'r', false, $ctx);
        if (! $handle) {
            echo "WARN: Gagal fetch: {$path}\n";
            return null;
        }

        return $handle;
    }

    if (! file_exists($path)) {
        echo "WARN: File tidak ditemukan: {$path}\n";
        return null;
    }

    return fopen($path, 'r');
}

function parseInsertRows(string $buffer): array
{
    preg_match_all(
        "/\\('([^']+)',\\s*'([^']*)',\\s*([\\d.eE+-]+),\\s*([\\d.eE+-]+),\\s*'(.*?)(?<!\\\\)'\\)/s",
        $buffer,
        $matches,
        PREG_SET_ORDER
    );

    return array_map(static fn (array $match): array => [
        'code' => $match[1],
        'name' => $match[2],
        'lat' => (float) $match[3],
        'lng' => (float) $match[4],
        'path' => $match[5],
    ], $matches);
}

function buildKelSources(string $inputBase, bool $isUrl, string $prefix, array $regencyCodesByProvince): array
{
    $sources = [];

    foreach ($regencyCodesByProvince as $provinceCode => $regencyCodes) {
        foreach (array_unique($regencyCodes) as $regencyCode) {
            $base = "{$inputBase}/kel/{$provinceCode}";
            $sources[] = [
                'source' => "{$base}/{$prefix}{$regencyCode}.sql",
            ];
        }
    }

    return $sources;
}

function writeDatasetRows($datasetHandle, array $rows, int $level): int
{
    foreach ($rows as $row) {
        gzwrite($datasetHandle, json_encode([
            'code' => $row['code'],
            'name' => $row['name'],
            'lat' => $row['lat'],
            'lng' => $row['lng'],
            'level' => $level,
            'path' => $row['path'],
            'status' => 1,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
    }

    return count($rows);
}

foreach ($LEVELS as $levelName => $config) {
    $sources = match ($levelName) {
        'prov' => array_map(
            static fn (int $index): array => [
                'source' => "{$inputBase}/prov/{$config['prefix']}{$index}.sql",
            ],
            range(1, 8)
        ),
        'kab', 'kec' => array_map(
            static fn (string $provinceCode): array => [
                'source' => "{$inputBase}/{$levelName}/{$config['prefix']}{$provinceCode}.sql",
            ],
            $PROVINCE_CODES
        ),
        'kel' => buildKelSources($inputBase, $isUrl, $config['prefix'], $regencyCodesByProvince),
    };

    echo "[{$levelName}] Memproses ".count($sources)." file...\n";

    $levelRecords = 0;

    foreach ($sources as $file) {
        $handle = openStream($file['source']);
        if (! $handle) {
            continue;
        }

        $buffer = '';
        $rows = [];
        $inInsert = false;

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line);

            if (stripos($line, 'INSERT INTO') !== false) {
                $buffer = $line;
                $inInsert = true;
            } elseif ($inInsert) {
                $buffer .= ' '.$line;
            }

            if ($inInsert && str_ends_with(rtrim($buffer), ';')) {
                $rows = array_merge($rows, parseInsertRows($buffer));
                $buffer = '';
                $inInsert = false;
            }
        }
        fclose($handle);

        if ($rows === []) {
            continue;
        }

        if ($levelName === 'kab') {
            foreach ($rows as $row) {
                $provinceCode = substr($row['code'], 0, 2);
                $regencyCodesByProvince[$provinceCode][] = $row['code'];
            }
        }

        $levelRecords += writeDatasetRows($datasetHandle, $rows, $config['level_id']);
    }

    echo "   ✓ {$levelRecords} record di {$levelName}\n";
    $countsByLevel[$levelName] = $levelRecords;
    $totalRecords += $levelRecords;
}

gzclose($datasetHandle);

$hash = getenv('UPSTREAM_HASH') ?: 'unknown';
$versionContent = "<?php\n// Auto-generated by CI/CD. DO NOT EDIT.\nreturn [\n"
    ."    'version' => '".date('Y.m.d')."',\n"
    ."    'data_date' => '".date('Y-m-d')."',\n"
    ."    'source_hash' => '{$hash}',\n"
    ."    'generated_at' => '".gmdate('c')."',\n"
    ."    'source' => [\n"
    ."        'repo' => 'cahyadsn/wilayah_boundaries',\n"
    ."        'branch' => 'main',\n"
    ."        'hash' => '{$hash}',\n"
    ."    ],\n"
    ."    'asset' => [\n"
    ."        'name' => 'boundaries-dataset.ndjson.gz',\n"
    ."        'url' => '',\n"
    ."        'format' => 'ndjson.gz',\n"
    ."        'size_bytes' => ".(file_exists(DATASET_PATH) ? filesize(DATASET_PATH) : 0).",\n"
    ."    ],\n"
    ."    'counts' => [\n"
    ."        'prov' => {$countsByLevel['prov']},\n"
    ."        'kab' => {$countsByLevel['kab']},\n"
    ."        'kec' => {$countsByLevel['kec']},\n"
    ."        'kel' => {$countsByLevel['kel']},\n"
    ."        'total' => {$totalRecords},\n"
    ."    ],\n"
    ."];\n";

file_put_contents(MANIFEST_PATH, $versionContent);

echo "\n=== Selesai: {$totalRecords} total batas wilayah ===\n";
