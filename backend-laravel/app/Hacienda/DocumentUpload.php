<?php

namespace App\Hacienda;

use App\News\NewsImageEncoder;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use RuntimeException;

final class DocumentUpload
{
    /** @return array{stream: resource, extension: string, mime: string, size: int, original: string} */
    public static function prepare(UploadedFile $file, NewsImageEncoder $encoder): array
    {
        $size = $file->getSize();
        if ($size < 1 || $size > DocumentStore::MAX_BYTES) {
            throw new InvalidArgumentException;
        }
        $original = preg_replace('/[\x00-\x1f\x7f-\x9f]/u', '', basename(str_replace('\\', '/', $file->getClientOriginalName())));
        $original = $original !== null && preg_match('/\S/u', $original) ? $original : 'Documento';
        $original = mb_convert_encoding(mb_strcut(mb_convert_encoding($original, 'UTF-16LE', 'UTF-8'), 0, 510, 'UTF-16LE'), 'UTF-8', 'UTF-16LE');
        $path = $file->getPathname();
        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException;
        }
        $retained = false;
        try {
            if (fread($stream, 5) !== DocumentStore::SIGNATURES['pdf']) {
                return [...$encoder->encode($path), 'original' => $original];
            }
            $header = fgets($stream, 16);
            if ($header === false || preg_match('/^(?:1\.[0-7]|2\.0)(?:\r\n?|\n)/D', $header) !== 1
                || fseek($stream, -min(1024, $size), SEEK_END) !== 0
                || preg_match('/%%EOF[\x00\x09-\x0d\x20]*$/D', stream_get_contents($stream, 1024)) !== 1
                || ! rewind($stream)) {
                throw new InvalidArgumentException;
            }
            $retained = true;

            return ['stream' => $stream, 'extension' => 'pdf', 'mime' => 'application/pdf', 'size' => $size, 'original' => $original];
        } finally {
            if (! $retained) {
                fclose($stream);
            }
        }
    }
}
