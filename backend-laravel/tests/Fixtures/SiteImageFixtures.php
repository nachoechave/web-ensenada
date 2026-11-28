<?php

namespace Tests\Fixtures;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

final class SiteImageFixtures
{
    public const UPLOAD = '/api/admin/archivos/sitio';

    public static function raster(string $format, int $width = 64, int $height = 48): string
    {
        if ($format === 'rgba') {
            return NewsImageFixtures::bytes('png', $width, $height);
        }
        $image = imagecreatetruecolor($width, $height);
        for ($row = 0; $row < $height; $row += 17) {
            for ($column = 0; $column < $width; $column += 19) {
                $color = (($row * 41 + $column * 19) & 255) << 16
                    | (($row * 29 + $column * 31) & 255) << 8
                    | (($row * 13 + $column * 47) & 255);
                imagefilledrectangle($image, $column, $row, $column + 18, $row + 16, $color);
            }
        }
        ob_start();
        match ($format) {
            'jpg' => imagejpeg($image, quality: 88),
            'png' => imagepng($image),
            'gif' => imagegif($image),
            'bmp' => imagebmp($image, compressed: false),
            'wbmp' => imagewbmp($image),
        };

        return ob_get_clean();
    }

    public static function tiff(): string
    {
        // Baseline little-endian TIFF, 2 × 2 grayscale, a single uncompressed strip.
        $entries = [
            256 => [4, 2], 257 => [4, 2], 258 => [3, 8], 259 => [3, 1], 262 => [3, 1],
            273 => [4, 122], 277 => [3, 1], 278 => [4, 2], 279 => [4, 4],
        ];
        $data = "II\x2a\x00".pack('Vv', 8, count($entries));
        foreach ($entries as $tag => [$type, $value]) {
            $data .= pack('vvVV', $tag, $type, 1, $value);
        }

        return $data.pack('V', 0)."\x00\x33\x99\xff";
    }

    public static function alphaPng(int $width, int $height, bool $noise): string
    {
        $deflate = deflate_init(ZLIB_ENCODING_DEFLATE);
        $plain = str_repeat("\x40\x60\x80\xff", $width);
        $data = '';
        for ($row = 0; $row < $height; $row++) {
            $data .= deflate_add($deflate, "\x00".($noise ? random_bytes($width * 4) : $plain));
        }
        $data .= deflate_add($deflate, '', ZLIB_FINISH);
        $chunk = static fn (string $type, string $content): string => pack('N', strlen($content)).$type.$content.pack('N', crc32($type.$content));

        return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0))
            .$chunk('IDAT', $data).$chunk('IEND', '');
    }

    public static function post(Client $client, string $bytes, array $headers = []): ResponseInterface
    {
        return $client->post(self::UPLOAD, ['multipart' => [[
            'name' => 'archivo', 'contents' => $bytes, 'filename' => '../../sintetico.php',
            'headers' => ['Content-Type' => 'text/html'],
        ]], 'headers' => $headers]);
    }
}
