<?php

namespace Tests\Fixtures;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

final class NewsImageFixtures
{
    public const UPLOAD = '/api/admin/archivos/noticias';

    public static function bytes(string $extension, int $width = 32, int $height = 24): string
    {
        $image = imagecreatetruecolor($width, $height);
        if ($extension === 'png') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 15, 75, 155, 53));
        imagefilledrectangle($image, 2, 2, intdiv($width, 2), intdiv($height, 2), imagecolorallocate($image, 200, 90, 40));
        ob_start();
        $extension === 'png' ? imagepng($image, null, 6) : imagejpeg($image, null, 81);

        return ob_get_clean();
    }

    public static function post(Client $client, string $bytes, string $name = 'sintetico.jpg', string $mime = 'image/jpeg', array $headers = []): ResponseInterface
    {
        return $client->post(self::UPLOAD, ['multipart' => [[
            'name' => 'archivo', 'contents' => $bytes, 'filename' => $name, 'headers' => ['Content-Type' => $mime],
        ]], 'headers' => $headers]);
    }
}
