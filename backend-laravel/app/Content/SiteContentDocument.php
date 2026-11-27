<?php

namespace App\Content;

use JsonException;
use stdClass;

final class SiteContentDocument
{
    public const MAX_BYTES = 65_535;

    private const LINK_ITEM = ['titulo' => 'string', 'descripcion' => 'string', 'url' => 'link', 'icono' => 'string'];

    private const SHAPE = [
        'topbar' => [
            'telefono' => 'string', 'direccion' => 'string', 'horarios' => 'string',
            'facebook' => 'link', 'instagram' => 'link', 'youtube' => 'link',
        ],
        'navbar' => [
            'inicio' => 'string', 'municipio' => 'string', 'areas' => 'string', 'tramites' => 'string',
            'noticias' => 'string', 'hacienda' => 'string', 'contacto' => 'string',
            'boletinTexto' => 'string', 'boletinUrl' => 'link',
            'items' => [['texto' => 'string', 'url' => 'link', 'visible' => 'boolean']],
        ],
        'hero' => [
            'eyebrow' => 'string', 'titulo' => 'string', 'bajada' => 'string',
            'imagen' => 'css-image', 'imagenes' => ['css-image'], 'intervaloSegundos' => 'number',
            'ctaPrimarioTexto' => 'string', 'ctaPrimarioUrl' => 'link',
            'ctaSecundarioTexto' => 'string', 'ctaSecundarioUrl' => 'link',
        ],
        'accesosTitulo' => 'string',
        'accesos' => [self::LINK_ITEM],
        'tramites' => ['kicker' => 'string', 'titulo' => 'string', 'bajada' => 'string', 'items' => [self::LINK_ITEM]],
        'noticias' => ['kicker' => 'string', 'titulo' => 'string', 'bajada' => 'string', 'boton' => 'string'],
        'areas' => [
            'kicker' => 'string', 'titulo' => 'string', 'bajada' => 'string',
            'paginaTitulo' => 'string', 'paginaBajada' => 'string', 'items' => [self::LINK_ITEM],
            'secretarias' => [[
                'id' => 'string', 'nombre' => 'string', 'responsable' => 'string', 'direccion' => 'string',
                'telefono' => 'string', 'horario' => 'string', 'email' => 'string',
            ]],
        ],
        'intendencia' => [
            'kicker' => 'string', 'nombre' => 'string', 'descripcion' => 'string', 'imagen' => 'image',
            'botonTexto' => 'string', 'botonUrl' => 'link', 'secundarioTexto' => 'string',
            'secundarioUrl' => 'link', 'cita' => 'string', 'historiaTitulo' => 'string',
            'historia' => 'string', 'galeria' => ['image'],
        ],
        'agenda' => [
            'kicker' => 'string', 'titulo' => 'string', 'bajada' => 'string',
            'items' => [['dia' => 'string', 'mes' => 'string', 'titulo' => 'string', 'lugar' => 'string', 'hora' => 'string']],
        ],
        'footer' => [
            'descripcion' => 'string', 'telefono' => 'string', 'direccion' => 'string',
            'email' => 'string', 'mapaUrl' => 'link', 'copyright' => 'string',
        ],
    ];

    public static function valid(string $json): bool
    {
        try {
            return self::matches(json_decode($json, flags: JSON_THROW_ON_ERROR), self::SHAPE);
        } catch (JsonException) {
            return false;
        }
    }

    private static function matches(mixed $value, array|string $shape): bool
    {
        if (is_string($shape)) {
            return match ($shape) {
                'string' => is_string($value),
                'number' => (is_int($value) || is_float($value)) && is_finite($value),
                'boolean' => is_bool($value),
                'link', 'image', 'css-image' => is_string($value) && self::safeUrl($value, $shape),
            };
        }
        if (array_is_list($shape)) {
            if (! is_array($value)) {
                return false;
            }
            foreach ($value as $item) {
                if (! self::matches($item, $shape[0])) {
                    return false;
                }
            }

            return true;
        }
        if (! $value instanceof stdClass) {
            return false;
        }
        foreach ($shape as $key => $fieldShape) {
            if (property_exists($value, $key) && ! self::matches($value->{$key}, $fieldShape)) {
                return false;
            }
        }

        return true;
    }

    private static function safeUrl(string $url, string $context): bool
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $url) || str_contains($url, '\\')
            || ($context === 'css-image' && strpbrk($url, "\"'()<>") !== false)) {
            return false;
        }
        $url = trim($url);
        if (str_starts_with($url, '//')) {
            return false;
        }
        if (! preg_match('~^[^/?#]*:~', $url)) {
            return true;
        }
        if ($context === 'link' && preg_match('/^(mailto|tel):\S+$/iD', $url)) {
            return true;
        }

        $parts = parse_url($url);

        return preg_match('~^https?://~i', $url) && ! empty($parts['host'])
            && ! isset($parts['user']) && ! isset($parts['pass']);
    }
}
