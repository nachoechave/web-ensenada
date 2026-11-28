<?php

namespace App\Hacienda;

use JsonException;

final class PublicationInput
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

        $title = $body->titulo ?? null;
        $description = $body->descripcion ?? null;
        $type = $body->tipo ?? null;
        $date = $body->fechaPublicacion ?? null;
        $state = $body->estado ?? null;
        if (! self::text($title, 255) || preg_match('/\S/u', trim($title, "\x00..\x20")) !== 1 || ! self::text($description, 10000)
            || ! in_array($type, ['SITUACION_ECONOMICO_FINANCIERA', 'PRESUPUESTO', 'ORDENANZA_FISCAL',
                'ORDENANZA_PRESUPUESTARIA', 'CIERRE_EJERCICIO', 'OTRO'], true)
            || ! in_array($state, ['BORRADOR', 'PUBLICADA', 'ARCHIVADA'], true)
            || ! is_string($date) || ! preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $date, $parts)
            || (int) $parts[1] < 1000 || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return null;
        }

        return [
            'titulo' => trim($title, "\x00..\x20"),
            'descripcion' => trim($description, "\x00..\x20"),
            'tipo' => $type,
            'fecha_publicacion' => $date,
            'estado' => $state,
        ];
    }

    public static function id(string $value): ?int
    {
        if (! preg_match('/^[+-]?[0-9]+$/D', $value)) {
            return null;
        }
        $number = filter_var(($value[0] === '-' ? '-' : '').(ltrim($value, '+-0') ?: '0'), FILTER_VALIDATE_INT);

        return $number === false ? null : $number;
    }

    private static function text(mixed $value, int $limit): bool
    {
        return is_string($value) && ! str_contains($value, "\0")
            && strlen(mb_convert_encoding($value, 'UTF-16LE', 'UTF-8')) <= $limit * 2;
    }
}
