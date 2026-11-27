<?php

namespace App\Http\Controllers;

use App\Auth\JwtSessions;
use App\Content\SiteImageEncoder;
use App\News\NewsDto;
use App\News\NewsImageStore;
use App\Support\IwindsPublishedImages;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class NewsImageController extends Controller
{
    public function store(Request $request, SiteImageEncoder $encoder, NewsImageStore $store, JwtSessions $sessions): Response
    {
        $type = trim(explode(';', $request->header('Content-Type', ''), 2)[0]);
        if (strcasecmp($type, 'multipart/form-data') !== 0) {
            return NewsDto::error($request, 415);
        }
        $file = $request->file('archivo');
        if ($file instanceof UploadedFile) {
            if ($file->getError() === UPLOAD_ERR_INI_SIZE) {
                return response('', 413);
            }
            if (in_array($file->getError(), [UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE], true)) {
                return $this->storageError($request);
            }
        }
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return NewsDto::error($request, 400);
        }

        try {
            $image = $encoder->encode($file->getPathname(), function ($source, string $extension, int $size) use ($request, $sessions, $store): string {
                $actor = $sessions->authenticate($sessions->token($request));
                if ($actor === null || ! $actor->hasAnyRole(['PRENSA', 'SUPER_ADMIN'])) {
                    throw new RuntimeException(code: $actor === null ? 401 : 403);
                }

                return $store->put($source, $extension, $size);
            }, allowLegacy: false);
            $filename = $image['filename'];

            return response()->json([
                'url' => '/uploads/noticias/'.$filename,
                'nombreOriginal' => $filename,
                'contentType' => $image['mime'],
                'size' => $image['size'],
            ]);
        } catch (InvalidArgumentException $error) {
            return NewsDto::error($request, $error->getCode() === 413 ? 413 : 400, $error->getMessage());
        } catch (RuntimeException $error) {
            return in_array($error->getCode(), [401, 403, 503], true)
                ? NewsDto::error($request, $error->getCode()) : $this->storageError($request);
        }
    }

    public function show(Request $request, NewsImageStore $store, string $filename): Response
    {
        if (! IwindsPublishedImages::allows($request, 'noticias', $filename)) {
            return NewsDto::error($request, 404);
        }
        $opened = $store->open($filename);
        if ($opened === null) {
            return NewsDto::error($request, 404);
        }
        [$stream, $size] = $opened;
        $headers = ['Content-Type' => $store::mime(pathinfo($filename, PATHINFO_EXTENSION)), 'Content-Length' => (string) $size];
        if ($request->isMethod('HEAD')) {
            fclose($stream);

            return response('', 200, $headers);
        }

        return response()->stream(static function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, $headers);
    }

    private function storageError(Request $request): Response
    {
        Log::warning('Falló el almacenamiento de una imagen de noticias.');

        return NewsDto::error($request, 500);
    }
}
