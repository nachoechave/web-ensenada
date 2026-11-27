<?php

use App\Hacienda\DocumentStore;
use App\Hacienda\DocumentUpload;
use App\News\NewsImageEncoder;
use Illuminate\Http\UploadedFile;
use Tests\Fixtures\HaciendaDocuments;
use Tests\Fixtures\NewsImageFixtures;

require __DIR__.'/../../vendor/autoload.php';

$fixtures = '/tmp/hacienda-6b-fixtures';
$directory = DocumentStore::DIRECTORY;
$store = new DocumentStore;
$mode = $argv[1] ?? '';

if ($mode === 'fixtures') {
    mkdir($fixtures, 0700);
    mkdir($directory, 0700, true);
    file_put_contents($fixtures.'/pdf', HaciendaDocuments::pdf(DocumentStore::MAX_BYTES));
    foreach (['jpg', 'png'] as $extension) {
        file_put_contents($fixtures.'/'.$extension, NewsImageFixtures::bytes($extension, 5000, 4000));
    }
    exit(0);
}

if ($mode === 'disk-full') {
    $fill = $directory.'/.synthetic-fill';
    $pdf = HaciendaDocuments::pdf();
    $space = disk_free_space($directory);
    if ($space === false || $space > 64 * 1024 * 1024) {
        throw new RuntimeException('Se requiere un tmpfs sintético de hasta 64 MiB.');
    }
    $target = fopen($fill, 'xb');
    $source = fopen('php://temp', 'w+b');
    $block = str_repeat('0', 1024 * 1024);
    fwrite($source, $pdf);
    try {
        while (@fwrite($target, $block) === strlen($block)) {
            // Fill only the bounded tmpfs used by this disposable benchmark.
        }
        rewind($source);
        try {
            $store->put($source, 'pdf', strlen($pdf));
            throw new LogicException('La escritura debía fallar.');
        } catch (RuntimeException) {
            if (scandir($directory) !== ['.', '..', '.synthetic-fill']) {
                throw new LogicException('Se publicó un archivo parcial.');
            }
        }
    } finally {
        fclose($target);
        unlink($fill);
    }
    rewind($source);
    $name = $store->put($source, 'pdf', strlen($pdf));
    fclose($source);
    $store->removeUnassociatedUpload($name);
    echo 'ENOSPC sin archivos parciales y escritura posterior correcta'.PHP_EOL;
    exit(0);
}

if (! in_array($mode, ['pdf', 'jpg', 'png'], true)) {
    throw new InvalidArgumentException('Formato sintético desconocido.');
}
$path = $fixtures.'/'.$mode;
$before = getrusage();
$start = hrtime(true);
$result = DocumentUpload::prepare(new UploadedFile($path, 'fixture.'.$mode, test: true), new NewsImageEncoder);
$name = $store->put($result['stream'], $result['extension'], $result['size']);
fclose($result['stream']);
[$input, $size] = $store->open($name);
$output = fopen('/dev/null', 'wb');
if (stream_copy_to_stream($input, $output, $size) !== $size) {
    throw new RuntimeException('La descarga sintética quedó incompleta.');
}
fclose($input);
fclose($output);
$store->removeUnassociatedUpload($name);
$after = getrusage();
$cpu = static fn (array $r): float => ($r['ru_utime.tv_sec'] + $r['ru_stime.tv_sec']) * 1000
    + ($r['ru_utime.tv_usec'] + $r['ru_stime.tv_usec']) / 1000;
echo json_encode([
    'format' => $mode, 'input_bytes' => filesize($path), 'output_bytes' => $size,
    'pixels' => $mode === 'pdf' ? null : 20_000_000, 'php_peak_bytes' => memory_get_peak_usage(true),
    'rss_kib' => $after['ru_maxrss'], 'elapsed_ms' => round((hrtime(true) - $start) / 1_000_000, 2),
    'cpu_ms' => round($cpu($after) - $cpu($before), 2), 'php' => PHP_VERSION,
    'gd' => gd_info()['GD Version'], 'memory_limit' => ini_get('memory_limit'),
], JSON_THROW_ON_ERROR).PHP_EOL;
