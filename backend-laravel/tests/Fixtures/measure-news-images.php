<?php

use App\News\NewsImageEncoder;
use Tests\Fixtures\NewsImageFixtures;

require __DIR__.'/../../vendor/autoload.php';

if (isset($argv[1])) {
    $start = hrtime(true);
    $initial = getrusage();
    $result = (new NewsImageEncoder)->encode($argv[1]);
    $usage = getrusage();
    $cpu = static fn (array $r): float => ($r['ru_utime.tv_sec'] + $r['ru_stime.tv_sec']) * 1000
        + ($r['ru_utime.tv_usec'] + $r['ru_stime.tv_usec']) / 1000;
    echo json_encode([
        'format' => $result['mime'], 'pixels' => 20_000_000, 'input_bytes' => filesize($argv[1]),
        'output_bytes' => $result['size'], 'php_peak_bytes' => memory_get_peak_usage(true),
        'peak_rss_kib' => $usage['ru_maxrss'], 'elapsed_ms' => round((hrtime(true) - $start) / 1_000_000, 2),
        'cpu_ms' => round($cpu($usage) - $cpu($initial), 2), 'php' => PHP_VERSION, 'gd' => gd_info()['GD Version'],
        'memory_limit' => ini_get('memory_limit'),
    ], JSON_THROW_ON_ERROR).PHP_EOL;
    fclose($result['stream']);
    exit(0);
}

foreach (['jpg', 'png'] as $format) {
    $path = tempnam(sys_get_temp_dir(), 'synthetic-image-');
    try {
        file_put_contents($path, NewsImageFixtures::bytes($format, 5000, 4000));
        $process = proc_open([PHP_BINARY, __FILE__, $path], [STDIN, STDOUT, STDERR], $pipes);
        if (! is_resource($process) || proc_close($process) !== 0) {
            throw new RuntimeException('Falló la medición de GD.');
        }
    } finally {
        unlink($path);
    }
}
