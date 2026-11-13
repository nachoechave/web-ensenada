<?php

namespace App\Support;

use App\Auth\JwtSessions;
use App\Content\SiteContentDocument;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class IwindsPublishedImages
{
    public const PREVIEW_COOKIE = '__Secure-WEB_ENSENADA_IMAGE_PREVIEW';

    public static function encodePreview(string $token): string
    {
        return $token === '' ? '' : Crypt::encryptString('staging-image:'.$token);
    }

    public static function allows(Request $request, string $module, string $filename): bool
    {
        if (! defined('ENSENADA_IWINDS_STAGING_MODE') || ENSENADA_IWINDS_STAGING_MODE !== 'web') {
            return true;
        }
        $path = '/uploads/'.$module.'/'.$filename;
        $published = $module === 'noticias' ? self::news($path) : self::site($path);
        if ($published) {
            return true;
        }

        // The Angular editor's image requests need a separate cookie. Never use it for API actions.
        if ($request->header('Sec-Fetch-Site') !== 'same-origin'
            || IwindsPublicProbe::activeAdminPeriodExpiry(IwindsStaging::ROOT) === null) {
            return false;
        }
        $cookie = $request->cookie(self::PREVIEW_COOKIE);
        if (! is_string($cookie) || strlen($cookie) > 4096) {
            return false;
        }
        try {
            $decoded = Crypt::decryptString($cookie);
        } catch (DecryptException) {
            return false;
        }
        $roles = $module === 'noticias' ? ['PRENSA', 'SUPER_ADMIN'] : ['SUPER_ADMIN'];

        return str_starts_with($decoded, 'staging-image:')
            && app(JwtSessions::class)->authenticate(substr($decoded, strlen('staging-image:')))?->hasAnyRole($roles) === true;
    }

    private static function news(string $path): bool
    {
        $absolute = IwindsStaging::ORIGIN.$path;
        $matches = [$path, $absolute];
        $gallery = DB::table('noticia_imagenes as i')->join('noticias as n', 'n.id', '=', 'i.noticia_id')
            ->where('n.estado', 'PUBLICADA')->whereIn('i.url', $matches)->get(['i.url', 'n.estado']);

        return $gallery->contains(fn (object $row): bool => $row->estado === 'PUBLICADA' && in_array($row->url, $matches, true))
            || DB::table('noticias')->where('estado', 'PUBLICADA')->whereIn('imagen', $matches)->get(['imagen', 'estado'])
                ->contains(fn (object $row): bool => $row->estado === 'PUBLICADA' && in_array($row->imagen, $matches, true));
    }

    private static function site(string $path): bool
    {
        $json = DB::table('portal_site_content')->where('id', 1)->value('contenido_json');
        if (! is_string($json) || ! SiteContentDocument::valid($json)) {
            return false;
        }
        $content = json_decode($json, true);
        $images = [
            $content['hero']['imagen'] ?? null, $content['intendencia']['imagen'] ?? null,
            ...($content['hero']['imagenes'] ?? []), ...($content['intendencia']['galeria'] ?? []),
        ];

        return in_array($path, $images, true) || in_array(IwindsStaging::ORIGIN.$path, $images, true);
    }
}
