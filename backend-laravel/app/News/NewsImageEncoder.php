<?php

namespace App\News;

use Closure;
use ErrorException;
use GdImage;
use InvalidArgumentException;
use RuntimeException;

final class NewsImageEncoder
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    private const MAX_PIXELS = 20_000_000;

    /** @return array{stream: resource, extension: string, mime: string, size: int} */
    public function encode(string $path): array
    {
        $size = @filesize($path);
        if ($size === false || $size < 1 || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException;
        }
        $previousTimeout = (int) ini_get('max_execution_time');
        set_time_limit(8);
        try {
            [$image, $extension] = $this->decode($path);

            return $this->encodePixels($image, $extension);
        } finally {
            set_time_limit($previousTimeout);
        }
    }

    private function decode(string $path): array
    {
        try {
            return $this->strictGd(function () use ($path): array {
                $shape = getimagesize($path);
                if ($shape === false || ! in_array($shape[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
                    throw new InvalidArgumentException;
                }
                [$width, $height, $type] = $shape;
                if ($width < 1 || $height < 1 || $width > intdiv(self::MAX_PIXELS, $height)) {
                    throw new InvalidArgumentException;
                }
                // Budget two RGBA buffers plus encoder and response headroom before allocating pixels.
                $limit = ini_parse_quantity((string) ini_get('memory_limit'));
                $needed = 8 * $width * $height + 32 * 1024 * 1024;
                if ($limit !== -1 && $needed > $limit - memory_get_usage(true)) {
                    throw new InvalidArgumentException;
                }
                $jpeg = $type === IMAGETYPE_JPEG;
                $image = $jpeg ? imagecreatefromjpeg($path) : imagecreatefrompng($path);
                if ($image === false || imagesx($image) !== $width || imagesy($image) !== $height) {
                    throw new InvalidArgumentException;
                }

                return [$image, $jpeg ? 'jpg' : 'png'];
            });
        } catch (ErrorException) {
            throw new InvalidArgumentException;
        }
    }

    private function encodePixels(GdImage $image, string $extension): array
    {
        $stream = @fopen('php://temp/maxmemory:1048576', 'w+b');
        if ($stream === false) {
            throw new RuntimeException;
        }
        $ready = false;
        try {
            $jpeg = $extension === 'jpg';
            $encoded = $this->strictGd(function () use ($stream, $image, $jpeg): bool {
                if ($jpeg) {
                    return imagejpeg($image, $stream, 85);
                }

                return imagesavealpha($image, true) && imagepng($image, $stream, 6);
            });
            if (! $encoded || ! @fflush($stream)) {
                throw new RuntimeException;
            }
            $size = fstat($stream)['size'] ?? 0;
            $ending = $jpeg ? "\xFF\xD9" : "\x00\x00\x00\x00IEND\xAE\x42\x60\x82";
            // GD can report success even if the output stream stopped accepting data.
            if ($size < strlen($ending) || fseek($stream, -strlen($ending), SEEK_END) !== 0
                || fread($stream, strlen($ending)) !== $ending || ! rewind($stream)) {
                throw new RuntimeException;
            }
            if ($size > self::MAX_BYTES) {
                throw new InvalidArgumentException;
            }
            $ready = true;

            return ['stream' => $stream, 'extension' => $extension, 'mime' => $jpeg ? 'image/jpeg' : 'image/png', 'size' => $size];
        } catch (ErrorException) {
            throw new RuntimeException;
        } finally {
            if (! $ready) {
                fclose($stream);
            }
        }
    }

    private function strictGd(Closure $operation): mixed
    {
        set_error_handler(static fn () => throw new ErrorException('Error de imagen.'), E_WARNING | E_NOTICE);
        try {
            return $operation();
        } finally {
            restore_error_handler();
        }
    }
}
