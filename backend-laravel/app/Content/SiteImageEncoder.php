<?php

namespace App\Content;

use App\Images\LocalImageStore;
use App\Support\IwindsStaging;
use Closure;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

final class SiteImageEncoder
{
    public const MAX_INPUT_BYTES = 30 * 1024 * 1024;

    public const MAX_OUTPUT_BYTES = 5 * 1024 * 1024;

    public const SCRATCH = '/var/lib/ensenada-site-scratch';

    private const MAX_DIMENSION = 1920;

    private const FORMATS = [IMAGETYPE_JPEG => 'JPEG', IMAGETYPE_PNG => 'PNG', IMAGETYPE_WEBP => 'WEBP'];

    private const LEGACY = [
        IMAGETYPE_GIF => 'GIF', IMAGETYPE_BMP => 'BMP', IMAGETYPE_WBMP => 'WBMP',
        IMAGETYPE_TIFF_II => 'TIFF', IMAGETYPE_TIFF_MM => 'TIFF',
    ];

    /** @return array{filename: string, size: int, mime: string} */
    public function encode(string $input, Closure $store, bool $allowLegacy = true): array
    {
        $size = @filesize($input);
        if ($size === false || $size < 1) {
            throw new InvalidArgumentException('La imagen está vacía o no se pudo leer.');
        }
        if ($size > self::MAX_INPUT_BYTES) {
            throw new InvalidArgumentException('La imagen supera el límite de 30 MB.', 413);
        }
        $shape = @getimagesize($input);
        $type = $shape[2] ?? null;
        $formats = $allowLegacy ? self::FORMATS + self::LEGACY : self::FORMATS;
        if (! isset($formats[$type])) {
            throw new InvalidArgumentException('El formato no es compatible o el archivo está dañado. Seleccioná un JPEG, PNG o WebP.');
        }
        [$width, $height] = $shape;
        $maxPixels = $type === IMAGETYPE_JPEG ? 48_000_000 : 24_000_000;
        if ($width < 1 || $height < 1 || max($width, $height) > 12_000 || $width > intdiv($maxPixels, $height)) {
            throw new InvalidArgumentException('La foto supera el límite de dimensiones o píxeles (48 MP para JPEG, 24 MP para los demás formatos y 12.000 px por lado).');
        }
        if ($type === IMAGETYPE_WEBP) {
            $header = @file_get_contents($input, length: 30);
            if ($header === false || strlen($header) < 21 || unpack('V', $header, 4)[1] + 8 !== $size) {
                throw new InvalidArgumentException('El archivo WebP está dañado.');
            }
            if (substr($header, 12, 4) === 'VP8X' && (ord($header[20]) & 2) !== 0) {
                throw new InvalidArgumentException('Seleccioná un WebP sin animación.');
            }
        }

        $scratch = IwindsStaging::directory(self::SCRATCH);
        if (realpath($scratch) !== $scratch || (fileperms($scratch) & 0077) !== 0) {
            throw new RuntimeException;
        }
        $lock = @fopen($scratch.'/.worker', 'c');
        if ($lock === false) {
            throw new RuntimeException;
        }
        try {
            if (! flock($lock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('El procesador está ocupado.', 503);
            }
            $work = $scratch.'/'.bin2hex(random_bytes(16));
            if (! @mkdir($work, 0700)) {
                throw new RuntimeException;
            }
            try {
                $orientation = $this->metadataOrientation($input, $size, $type);

                return $this->optimize($input, $work, $width, $height, $formats[$type], $orientation, $store);
            } finally {
                foreach (glob($work.'/*') ?: [] as $file) {
                    @unlink($file);
                }
                @rmdir($work);
            }
        } finally {
            fclose($lock);
        }
    }

    private function optimize(string $input, string $work, int $width, int $height, string $format, ?string $orientation, Closure $store): array
    {
        $deadline = hrtime(true) + 20_000_000_000;
        $extension = $this->supportsWebp($work, $deadline) ? 'webp' : ($format === 'JPEG' ? 'jpg' : 'png');
        $coder = match ($extension) {
            'webp' => 'WEBP',
            'jpg' => 'JPEG',
            'png' => 'PNG',
        };

        $hint = [];
        if ($format === 'JPEG' && max($width, $height) >= self::MAX_DIMENSION * 2) {
            $scale = self::MAX_DIMENSION / max($width, $height);
            $hint = ['-define', 'jpeg:size='.max(1, (int) ceil($width * $scale)).'x'.max(1, (int) ceil($height * $scale))];
        }
        $output = $work.'/optimized';
        $orientationArguments = $orientation === null ? [] : ['-orient', $orientation];
        $inputArguments = [...$hint, $format.':'.$input.'[0]', ...$orientationArguments, '-auto-orient', '-colorspace', 'sRGB', '-strip', '-depth', '8'];
        $outputArguments = $extension === 'png' ? ['-define', 'png:compression-level=6'] : ['-quality', '82'];
        foreach ([self::MAX_DIMENSION, 1440] as $limit) {
            $this->convert($work, $deadline, [
                ...$inputArguments, '-resize', $limit.'x'.$limit.'>', ...$outputArguments, $coder.':'.$output,
            ]);
            clearstatcache(true, $output);
            $size = @filesize($output);
            if ($size === false || $size < 1) {
                throw new RuntimeException;
            }
            if ($size <= self::MAX_OUTPUT_BYTES) {
                return $this->publish($output, $size, $extension, $store);
            }
        }

        throw new InvalidArgumentException('La imagen optimizada supera el límite de almacenamiento.');
    }

    private function metadataOrientation(string $input, int $size, int $type): ?string
    {
        if (! in_array($type, [IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return null; // ImageMagick reads JPEG EXIF directly.
        }
        $stream = @fopen($input, 'rb');
        if ($stream === false) {
            return null;
        }
        $png = $type === IMAGETYPE_PNG;
        $offset = $png ? 8 : 12;
        try {
            for ($index = 0; $index < 512 && $offset <= $size - 8; $index++) {
                if (fseek($stream, $offset) !== 0 || strlen($header = fread($stream, 8) ?: '') !== 8) {
                    return null;
                }
                $chunk = unpack($png ? 'Nsize/a4name' : 'a4name/Vsize', $header);
                $length = $chunk['size'];
                if ($length > $size - $offset - 8) {
                    return null;
                }
                if ($chunk['name'] === ($png ? 'eXIf' : 'EXIF') && $length > 0) {
                    $tiff = fread($stream, min($length, 65_536)) ?: '';

                    return $this->tiffOrientation(str_starts_with($tiff, "Exif\0\0") ? substr($tiff, 6) : $tiff);
                }
                $offset += 8 + $length + ($png ? 4 : $length % 2);
            }

            return null;
        } finally {
            fclose($stream);
        }
    }

    private function tiffOrientation(string $data): ?string
    {
        $byteOrder = substr($data, 0, 2);
        if (strlen($data) < 8 || ! in_array($byteOrder, ['II', 'MM'], true)) {
            return null;
        }
        [$short, $long] = $byteOrder === 'II' ? ['v', 'V'] : ['n', 'N'];
        $offset = unpack($long, $data, 4)[1];
        if (unpack($short, $data, 2)[1] !== 42 || $offset < 8 || $offset > strlen($data) - 2) {
            return null;
        }
        $count = min(unpack($short, $data, $offset)[1], intdiv(strlen($data) - $offset - 2, 12), 256);
        for ($index = 0, $entry = $offset + 2; $index < $count; $index++, $entry += 12) {
            if (unpack($short, $data, $entry)[1] === 0x0112 && unpack($short, $data, $entry + 2)[1] === 3
                && unpack($long, $data, $entry + 4)[1] === 1) {
                $value = unpack($short, $data, $entry + 8)[1];

                return [1 => 'TopLeft', 'TopRight', 'BottomRight', 'BottomLeft', 'LeftTop', 'RightTop', 'RightBottom', 'LeftBottom'][$value] ?? null;
            }
        }

        return null;
    }

    private function supportsWebp(string $work, int $deadline): bool
    {
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGMQiWoCAAF2APEh5xLIAAAAAElFTkSuQmCC', true);
        if (@file_put_contents($work.'/probe.png', $pixel) !== strlen($pixel)) {
            throw new RuntimeException;
        }

        return $this->convert($work, $deadline, ['PNG:'.$work.'/probe.png', 'WEBP:'.$work.'/probe.webp'], optionalCoder: true)
            && (@getimagesize($work.'/probe.webp')['mime'] ?? null) === 'image/webp';
    }

    private function publish(string $output, int $size, string $extension, Closure $store): array
    {
        $shape = @getimagesize($output);
        if ($shape === false || max($shape[0], $shape[1]) > self::MAX_DIMENSION
            || $shape['mime'] !== LocalImageStore::mime($extension)) {
            throw new RuntimeException;
        }
        $source = @fopen($output, 'rb');
        if ($source === false) {
            throw new RuntimeException;
        }
        try {
            if ($extension === 'webp') {
                $header = fread($source, 12);
                $complete = strlen($header ?: '') === 12 && unpack('V', $header, 4)[1] + 8 === $size;
            } else {
                $end = $extension === 'png' ? "\x00\x00\x00\x00IEND\xae\x42\x60\x82" : "\xff\xd9";
                $complete = $size >= strlen($end) && fseek($source, -strlen($end), SEEK_END) === 0
                    && fread($source, strlen($end)) === $end;
            }
            if (! $complete || ! rewind($source)) {
                throw new RuntimeException;
            }

            return ['filename' => $store($source, $extension, $size), 'mime' => $shape['mime'], 'size' => $size];
        } finally {
            fclose($source);
        }
    }

    private function convert(string $work, int $deadline, array $arguments, bool $optionalCoder = false): bool
    {
        $remaining = ($deadline - hrtime(true)) / 1_000_000_000;
        if ($remaining <= 0) {
            throw new InvalidArgumentException('La foto excedió el tiempo de optimización. Probá con una de menor resolución.');
        }
        $env = ['MAGICK_TEMPORARY_PATH' => $work, 'LC_ALL' => 'C', 'OMP_NUM_THREADS' => '1'];
        if (app()->bound('config') && in_array(config('app.env'), ['staging', 'production'], true)) {
            $policy = base_path('deploy/iwinds');
            if (! is_readable($policy.'/policy.xml')) {
                throw new RuntimeException;
            }
            $env['MAGICK_CONFIGURE_PATH'] = $policy;
        }
        $process = new Process([
            '/usr/bin/prlimit', '--as=536870912', '--cpu=20', '--', '/usr/bin/convert', '-regard-warnings',
            '-limit', 'thread', '1', '-limit', 'memory', '48MiB', '-limit', 'map', '0', '-limit', 'disk', '192MiB',
            ...$arguments,
        ], $work, $env, timeout: $remaining);
        $process->disableOutput();
        try {
            $status = $process->run();
        } catch (ProcessTimedOutException) {
            throw new InvalidArgumentException('La foto excedió el tiempo de optimización. Probá con una de menor resolución.');
        }
        if ($status === 126 || $status === 127) {
            throw new RuntimeException;
        }
        if ($status !== 0 && ! $optionalCoder) {
            throw new InvalidArgumentException('No se pudo decodificar la imagen. Puede estar dañada, usar un formato no disponible o superar los recursos de optimización.');
        }

        return $status === 0;
    }
}
