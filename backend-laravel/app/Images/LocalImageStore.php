<?php

namespace App\Images;

use App\Support\IwindsStaging;
use Illuminate\Support\Str;
use RuntimeException;

abstract class LocalImageStore
{
    protected const EXTENSIONS = ['jpg', 'png'];

    public static function mime(string $extension): ?string
    {
        return match (strtolower($extension)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => null,
        };
    }

    /** @param resource $source */
    public function put($source, string $extension, int $size): string
    {
        if (! in_array($extension, static::EXTENSIONS, true) || ! $this->isPrivateDirectory()) {
            throw new RuntimeException;
        }

        $filename = Str::uuid()->toString().'.'.$extension;
        $temp = $this->directory().'/.stage-'.$filename;
        $destination = $this->directory().'/'.$filename;
        $file = @fopen($temp, 'xb');
        if ($file === false) {
            throw new RuntimeException;
        }
        try {
            if (! @chmod($temp, 0600) || @stream_copy_to_stream($source, $file, $size) !== $size
                || ! @fflush($file) || ! @fsync($file) || ! @link($temp, $destination)) {
                throw new RuntimeException;
            }

            return $filename;
        } finally {
            fclose($file);
            @unlink($temp);
        }
    }

    /** @return array{resource, int}|null */
    public function open(string $filename): ?array
    {
        if (! preg_match('~^'.static::FILENAME.'$~D', $filename) || ! $this->isPrivateDirectory()) {
            return null;
        }
        $path = $this->directory().'/'.$filename;
        $entry = @lstat($path);
        if ($entry === false || ($entry['mode'] & 0170000) !== 0100000 || $entry['nlink'] !== 1) {
            return null;
        }
        $file = @fopen($path, 'rb');
        if ($file === false) {
            return null;
        }
        $actual = fstat($file);
        if ($actual === false || $actual['ino'] !== $entry['ino'] || $actual['dev'] !== $entry['dev']
            || $actual['size'] < 1 || $actual['size'] > static::MAX_BYTES) {
            fclose($file);

            return null;
        }

        return [$file, $actual['size']];
    }

    protected function isPrivateDirectory(): bool
    {
        clearstatcache(true);

        $path = $this->directory();

        return realpath($path) === $path && is_dir($path) && (fileperms($path) & 0077) === 0;
    }

    protected function directory(): string
    {
        return IwindsStaging::directory(static::DIRECTORY);
    }
}
