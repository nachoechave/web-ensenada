<?php

namespace App\News;

use JsonException;

final class EditorialInput
{
    public static function parse(string $json): ?array
    {
        try {
            $body = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (! is_object($body)) {
            return null;
        }

        foreach (['titulo' => 255, 'bajada' => 500, 'contenido' => 60000, 'imagen' => 1000, 'categoria' => 255] as $field => $limit) {
            if (! self::validText($body->{$field} ?? null, $limit)) {
                return null;
            }
        }

        $date = $body->fechaPublicacion ?? null;
        if (! is_string($date) || ! preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $date, $parts)
            || (int) $parts[1] < 1000 || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
            || ! in_array($body->estado ?? null, ['BORRADOR', 'PUBLICADA', 'ARCHIVADA'], true)
            || (property_exists($body, 'destacada') && ! is_bool($body->destacada))
            || strlen($body->contenido) > 65535 || ! self::validImage($body->imagen)) {
            return null;
        }

        $images = $body->imagenes ?? [];
        if (! is_array($images) || count($images) > 6) {
            return null;
        }
        foreach ($images as $image) {
            if (! self::validText($image, 1000) || ! self::validImage($image)) {
                return null;
            }
        }
        $images = array_values(array_unique($images ?: [$body->imagen]));
        if (! in_array($body->imagen, $images, true)) {
            return null;
        }

        return [
            'fields' => [
                'titulo' => $body->titulo, 'bajada' => $body->bajada, 'contenido' => $body->contenido,
                'imagen' => $body->imagen, 'categoria' => $body->categoria, 'fecha_publicacion' => $date,
                'estado' => $body->estado, 'destacada' => $body->destacada ?? false,
            ],
            'images' => $images,
        ];
    }

    private static function validText(mixed $value, int $limit): bool
    {
        return is_string($value) && preg_match('/\S/u', $value) === 1 && ! str_contains($value, "\0")
            && strlen(mb_convert_encoding($value, 'UTF-16LE', 'UTF-8')) <= $limit * 2;
    }

    private static function validImage(string $url): bool
    {
        if (preg_match('~^/(?:uploads/noticias/[A-Za-z0-9._-]+|assets/[A-Za-z0-9/._-]+)$~D', $url)) {
            return ! preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $url);
        }
        if (! str_starts_with($url, 'https://') || preg_match('/[\s\x00-\x1f\x7f]/u', $url) || str_contains($url, '\\')) {
            return false;
        }
        $parsed = parse_url($url);

        return ! empty($parsed['host']) && ! isset($parsed['user']) && ! isset($parsed['pass']);
    }
}
