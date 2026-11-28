<?php

namespace App\Http;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class DownloadStream
{
    /** @param resource $source */
    public static function send(Request $request, $source, int $size, array $headers): Response
    {
        $range = self::range($request, $size);
        if ($range === false) {
            fclose($source);
            unset($headers['Content-Length']);

            return response('', 416, [...$headers, 'Content-Range' => 'bytes */'.$size]);
        }
        if ($request->isMethod('HEAD')) {
            fclose($source);

            return response('', 200, $headers);
        }
        [$start, $end] = $range ?? [0, $size - 1];
        $length = $end - $start + 1;
        if ($range !== null) {
            $headers['Content-Range'] = "bytes $start-$end/$size";
            $headers['Content-Length'] = (string) $length;
        }

        return response()->stream(static function () use ($source, $start, $length): void {
            $target = fopen('php://output', 'wb');
            try {
                stream_copy_to_stream($source, $target, $length, $start);
            } finally {
                fclose($source);
                fclose($target);
            }
        }, $range === null ? 200 : 206, $headers);
    }

    /** @return array{int, int}|false|null */
    private static function range(Request $request, int $size): array|false|null
    {
        $value = $request->header('Range', '');
        if (! $request->isMethod('GET') || $request->hasHeader('If-Range')
            || ! str_starts_with($value, 'bytes=') || str_contains($value, ',')) {
            return null;
        }
        if (preg_match('/^bytes=(\d*)-(\d*)$/D', $value, $parts) !== 1 || ($parts[1] === '' && $parts[2] === '')) {
            return false;
        }
        if ($parts[1] === '') {
            $length = (int) $parts[2];

            return $length === 0 ? false : [max(0, $size - $length), $size - 1];
        }
        $start = (int) $parts[1];
        $end = $parts[2] === '' ? $size - 1 : min((int) $parts[2], $size - 1);

        return $start > $end ? false : [$start, $end];
    }
}
