<?php

namespace Tests\Fixtures;

use Symfony\Component\Process\Process;

final class PhotoFixtures
{
    public static function orientationSix(string $jpeg): string
    {
        $exif = "Exif\0\0".self::tiff();

        return substr($jpeg, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
    }

    public static function orientedPng(string $png): string
    {
        $chunk = 'eXIf'.self::tiff();

        return substr($png, 0, 33).pack('N', strlen($chunk) - 4).$chunk.pack('N', crc32($chunk)).substr($png, 33);
    }

    public static function orientedWebp(string $webp): string
    {
        $content = substr($webp, 8);
        $content[12] = chr(ord($content[12]) | 8); // EXIF flag in the VP8X header.
        $tiff = self::tiff();
        $content .= 'EXIF'.pack('V', strlen($tiff)).$tiff;

        return 'RIFF'.pack('V', strlen($content)).$content;
    }

    public static function webp(string $png): string
    {
        return (new Process(['/usr/bin/convert', 'PNG:-', 'WEBP:-'], input: $png))->mustRun()->getOutput();
    }

    public static function firstPixelAlpha(string $webp): float
    {
        $process = new Process(['/usr/bin/convert', 'WEBP:-', '-format', '%[fx:p{0,0}.a]', 'INFO:-'], input: $webp);

        return (float) $process->mustRun()->getOutput();
    }

    private static function tiff(): string
    {
        return 'II'.pack('vVv', 42, 8, 1).pack('vvVvvV', 0x112, 3, 1, 6, 0, 0);
    }
}
