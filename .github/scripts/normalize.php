<?php

/**
 * Normalize SQL upstream wilayah_boundaries → PHP array data files.
 *
 * Mendukung 2 mode input:
 *
 * 1. RAW URL (CI/CD — tanpa git clone):
 *    php normalize.php \
 *      "https://raw.githubusercontent.com/cahyadsn/wilayah_boundaries/refs/heads/main/db/prov/wilayah_boundaries_prov_1.sql" \
 *      ...
 *
 * 2. Direktori lokal:
 *    php normalize.php /path/to/wilayah_boundaries-main/db
 *
 * Usage:
 *    normalize.php [db_dir_or_base_url] [--level=all|prov|kab|kec|kel]
 */

const UPSTREAM_BASE   = 'https://raw.githubusercontent.com/cahyadsn/wilayah_boundaries/refs/heads/main/db';
const OUTPUT_BASE_DIR = __DIR__ . '/../../data/boundaries';

// ─────────────────────────────────────────────
// Konfigurasi level & berkas SQL upstream
// ─────────────────────────────────────────────
$LEVELS = [
    'prov' => ['prefix' => 'wilayah_boundaries_prov_', 'count' => 8,   'level_id' => 1],
    'kab'  => ['prefix' => 'wilayah_boundaries_kab_',  'level_id' => 2],
    'kec'  => ['prefix' => 'wilayah_boundaries_kec_',  'level_id' => 3],
    'kel'  => ['prefix' => 'wilayah_boundaries_kel_',  'level_id' => 4],
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

// Input: direktori lokal atau base URL
$inputBase = $argv[1] ?? UPSTREAM_BASE;
$isUrl     = str_starts_with($inputBase, 'http');

$totalRecords = 0;
$regencyCodesByProvince = [];

echo "=== Normalizer Wilayah Boundaries ===\n";
echo "Input  : {$inputBase}\n";
echo "Output : " . OUTPUT_BASE_DIR . "\n\n";

@mkdir(OUTPUT_BASE_DIR, 0755, true);

// ─────────────────────────────────────────────
// Helper: buka stream (URL atau file)
// ─────────────────────────────────────────────
function openStream(string $path): mixed
{
    if (str_starts_with($path, 'http')) {
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'header'  => "User-Agent: aliziodev-boundaries-normalizer/1.0\r\n",
                'timeout' => 120,
            ],
        ]);
        $h = @fopen($path, 'r', false, $ctx);
        if (! $h) {
            echo "WARN: Gagal fetch: {$path}\n";
            return null;
        }
        return $h;
    }

    if (! file_exists($path)) {
        echo "WARN: File tidak ditemukan: {$path}\n";
        return null;
    }
    return fopen($path, 'r');
}

// ─────────────────────────────────────────────
// Helper: parse baris INSERT SQL
// ─────────────────────────────────────────────
function parseInsertRows(string $buffer): array
{
    $rows = [];
    // Cocokkan VALUES ('kode','nama',lat,lng,'path')
    preg_match_all(
        "/\\('([^']+)',\\s*'([^']*)',\\s*([\\d.eE+-]+),\\s*([\\d.eE+-]+),\\s*'(.*?)(?<!\\\\)'\\)/s",
        $buffer,
        $matches,
        PREG_SET_ORDER
    );
    foreach ($matches as $m) {
        $rows[] = [
            'code' => $m[1],
            'name' => $m[2],
            'lat'  => (float) $m[3],
            'lng'  => (float) $m[4],
            'path' => $m[5],  // JSON polygon
        ];
    }
    return $rows;
}

// ─────────────────────────────────────────────
// Proses setiap level
// ─────────────────────────────────────────────
foreach ($LEVELS as $levelName => $conf) {
    $sources = match ($levelName) {
        'prov' => array_map(
            fn (int $index): array => [
                'source' => ($isUrl ? "{$inputBase}/prov" : "{$inputBase}/prov") . "/{$conf['prefix']}{$index}.sql",
                'target' => "boundaries_{$levelName}_{$index}.php",
            ],
            range(1, 8)
        ),
        'kab', 'kec' => array_map(
            fn (string $provinceCode): array => [
                'source' => ($isUrl ? "{$inputBase}/{$levelName}" : "{$inputBase}/{$levelName}") . "/{$conf['prefix']}{$provinceCode}.sql",
                'target' => "boundaries_{$levelName}_{$provinceCode}.php",
            ],
            $PROVINCE_CODES
        ),
        'kel' => buildKelSources($inputBase, $isUrl, $conf['prefix'], $regencyCodesByProvince),
    };

    echo "[{$levelName}] Memproses " . count($sources) . " file...\n";

    @mkdir(OUTPUT_BASE_DIR . "/{$levelName}", 0755, true);

    $levelRecords = 0;

    foreach ($sources as $file) {
        $handle = openStream($file['source']);
        if (! $handle) {
            continue;
        }

        $buffer       = '';
        $rows         = [];
        $inInsert     = false;

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line);

            if (stripos($line, 'INSERT INTO') !== false) {
                $buffer   = $line;
                $inInsert = true;
            } elseif ($inInsert) {
                $buffer .= ' ' . $line;
            }

            if ($inInsert && str_ends_with(rtrim($buffer), ';')) {
                $rows   = array_merge($rows, parseInsertRows($buffer));
                $buffer = '';
                $inInsert = false;
            }
        }
        fclose($handle);

        if (empty($rows)) {
            continue;
        }

        if ($levelName === 'kab') {
            foreach ($rows as $row) {
                $provinceCode = substr($row['code'], 0, 2);
                $regencyCodesByProvince[$provinceCode][] = $row['code'];
            }
        }

        // Tulis PHP array — satu file per SQL upstream
        $content = "<?php\n// Auto-generated by CI/CD. DO NOT EDIT.\n// Source: cahyadsn/wilayah_boundaries\nreturn [\n";
        foreach ($rows as $r) {
            $pathJson = addslashes($r['path']);
            $content .= "    ['code'=>'{$r['code']}','name'=>" . var_export($r['name'], true)
                . ",'lat'=>{$r['lat']},'lng'=>{$r['lng']},'level'=>{$conf['level_id']},'path'=>'{$pathJson}'],\n";
        }
        $content .= "];\n";

        file_put_contents(OUTPUT_BASE_DIR . "/{$levelName}/{$file['target']}", $content);
        $levelRecords += count($rows);
    }

    echo "   ✓ {$levelRecords} record di {$levelName}\n";
    $totalRecords += $levelRecords;
}

// ─────────────────────────────────────────────
// version.php
// ─────────────────────────────────────────────
$hash = getenv('UPSTREAM_HASH') ?: 'unknown';

$versionContent = "<?php\n// Auto-generated by CI/CD. DO NOT EDIT.\nreturn [\n"
    . "    'version'     => '" . date('Y.m.d') . "',\n"
    . "    'data_date'   => '" . date('Y-m-d') . "',\n"
    . "    'source_hash' => '{$hash}',\n"
    . "    'generated_at' => '" . gmdate('c') . "',\n"
    . "    'source'      => [\n"
    . "        'repo' => 'cahyadsn/wilayah_boundaries',\n"
    . "        'branch' => 'main',\n"
    . "        'hash' => '{$hash}',\n"
    . "    ],\n"
    . "    'total'       => {$totalRecords},\n"
    . "];\n";

file_put_contents(OUTPUT_BASE_DIR . '/version.php', $versionContent);

echo "\n=== Selesai: {$totalRecords} total batas wilayah ===\n";

function buildKelSources(string $inputBase, bool $isUrl, string $prefix, array $regencyCodesByProvince): array
{
    $sources = [];

    foreach ($regencyCodesByProvince as $provinceCode => $regencyCodes) {
        foreach (array_unique($regencyCodes) as $regencyCode) {
            $base = $isUrl ? "{$inputBase}/kel/{$provinceCode}" : "{$inputBase}/kel/{$provinceCode}";
            $sources[] = [
                'source' => "{$base}/{$prefix}{$regencyCode}.sql",
                'target' => 'boundaries_kel_' . str_replace('.', '_', $regencyCode) . '.php',
            ];
        }
    }

    return $sources;
}
