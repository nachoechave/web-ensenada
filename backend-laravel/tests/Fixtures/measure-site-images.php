<?php

use App\Content\SiteImageEncoder;
use Tests\Fixtures\SiteImageFixtures;

require __DIR__.'/../../vendor/autoload.php';

$cases = ['jpeg', 'png', 'large-photo', 'transparent', 'dense-alpha', 'corrupt', 'near-limit', 'large-png-budget'];
$case = $argv[2] ?? '';
if (! in_array($case, $cases, true)) {
    throw new InvalidArgumentException('Seleccionar una muestra sintética: '.implode(', ', $cases));
}
$file = '/tmp/ensenada-site-measure-'.$case;
if (($argv[1] ?? '') === 'prepare') {
    $input = match ($case) {
        'jpeg' => SiteImageFixtures::raster('jpg', 1800, 1200),
        'png' => SiteImageFixtures::raster('png', 1800, 1200),
        'large-photo' => SiteImageFixtures::raster('jpg', 8000, 5500),
        'transparent' => SiteImageFixtures::raster('rgba', 3500, 2500),
        'dense-alpha' => SiteImageFixtures::alphaPng(1400, 1200, true),
        'corrupt' => substr(SiteImageFixtures::raster('jpg', 1800, 1200), 0, 2048),
        'near-limit' => str_pad(SiteImageFixtures::raster('jpg', 3000, 1800), SiteImageEncoder::MAX_INPUT_BYTES, 'x'),
        'large-png-budget' => SiteImageFixtures::alphaPng(12000, 12000, false),
    };
    file_put_contents($file, $input, LOCK_EX);
    exit;
}

$start = hrtime(true);
try {
    $result = (new SiteImageEncoder)->encode($file, static fn () => 'synthetic-measurement');
    $status = 'accepted';
} catch (InvalidArgumentException) {
    $result = null;
    $status = 'rejected';
}
$child = getrusage(1);
$cpu = ($child['ru_utime.tv_sec'] + $child['ru_stime.tv_sec']) * 1000
    + ($child['ru_utime.tv_usec'] + $child['ru_stime.tv_usec']) / 1000;
echo json_encode([
    'case' => $case, 'status' => $status, 'input_bytes' => filesize($file), 'output_bytes' => $result['size'] ?? null,
    'wall_ms' => round((hrtime(true) - $start) / 1e6, 2), 'child_cpu_ms' => round($cpu, 2),
    'php_peak_bytes' => memory_get_peak_usage(true), 'child_peak_rss_kib' => $child['ru_maxrss'], 'php_limit' => ini_get('memory_limit'),
], JSON_THROW_ON_ERROR).PHP_EOL;
unlink($file);
