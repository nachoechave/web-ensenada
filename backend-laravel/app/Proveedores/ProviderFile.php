<?php

namespace App\Proveedores;

use DOMDocument;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

final class ProviderFile
{
    private const CFB = "\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1";

    public static function original(string $name): string
    {
        $name = preg_replace('/[\x00-\x1f\x7f-\x9f]/u', '', basename(str_replace('\\', '/', $name))) ?? '';
        $name = preg_match('/\S/u', $name) ? $name : 'Documento';

        return mb_convert_encoding(mb_strcut(mb_convert_encoding($name, 'UTF-16LE', 'UTF-8'), 0, 510, 'UTF-16LE'), 'UTF-8', 'UTF-16LE');
    }

    /** @return array{resource, string, string, int} */
    public static function prepare(UploadedFile $file): array
    {
        $size = $file->getSize();
        if ($size < 1 || $size > ProviderStore::MAX_BYTES) {
            throw new InvalidArgumentException('El archivo debe pesar menos de 20 MB');
        }
        $original = self::original($file->getClientOriginalName());
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (! isset(ProviderStore::MIME[$extension])) {
            throw new InvalidArgumentException('Formato no permitido. Usá PDF, DOC, XLS o XLSX');
        }
        $stream = @fopen($file->getPathname(), 'rb');
        if ($stream === false) {
            throw new RuntimeException;
        }
        try {
            $header = fread($stream, 512);
            $valid = match ($extension) {
                'pdf' => preg_match('/^%PDF-(?:1\.[0-7]|2\.0)(?:\r\n?|\n)/', $header) === 1
                    && fseek($stream, -min(1024, $size), SEEK_END) === 0
                    && preg_match('/%%EOF[\x00\x09-\x0d\x20]*$/D', stream_get_contents($stream, 1024)) === 1,
                'xlsx' => str_starts_with($header, "PK\x03\x04") && self::spreadsheet($file->getPathname()),
                'doc', 'xls' => self::compound($stream, $header, $size, $extension),
            };
            if (! $valid) {
                throw new InvalidArgumentException(match ($extension) {
                    'pdf' => 'El archivo no es un PDF válido',
                    'xlsx' => 'El archivo no es un XLSX válido',
                    default => 'El archivo no es un documento Office válido',
                });
            }
            if (! rewind($stream)) {
                throw new RuntimeException;
            }

            return [$stream, $extension, $original, $size];
        } catch (\Throwable $error) {
            fclose($stream);
            throw $error;
        }
    }

    private static function spreadsheet(string $path): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            return false;
        }
        try {
            $entry = $zip->statName('[Content_Types].xml');
            if ($entry === false || $entry['size'] < 1 || $entry['size'] > 262144) {
                return false;
            }
            $xml = $zip->getFromIndex($entry['index'], $entry['size']);
            $document = new DOMDocument;
            if ($xml === false || ! @$document->loadXML($xml, LIBXML_NONET) || $document->doctype !== null) {
                return false;
            }
            $type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml';
            foreach ($document->getElementsByTagNameNS('http://schemas.openxmlformats.org/package/2006/content-types', 'Override') as $part) {
                if ($part->getAttribute('ContentType') === $type) {
                    $name = $part->getAttribute('PartName');
                    if (str_starts_with($name, '/') && ! preg_match('~(?:^|/)\.\.?(/|$)|[\\\\\x00-\x1f]~', $name)
                        && ($workbook = $zip->statName(substr($name, 1))) !== false) {
                        $prefix = $zip->getFromIndex($workbook['index'], 8192);
                        if (is_string($prefix) && preg_match('/<(?:[\w.-]+:)?workbook(?=[\s\/>])/', $prefix)) {
                            return true;
                        }
                    }
                }
            }

            return false;
        } finally {
            $zip->close();
        }
    }

    /** @param resource $stream */
    private static function compound($stream, string $header, int $size, string $extension): bool
    {
        if (strlen($header) !== 512 || ! str_starts_with($header, self::CFB)) {
            return false;
        }
        $sector = match (substr($header, 26, 8)) {
            "\x03\x00\xfe\xff\x09\x00\x06\x00" => 512,
            "\x04\x00\xfe\xff\x0c\x00\x06\x00" => 4096,
            default => 0,
        };
        if ($sector === 0 || $size < 3 * $sector || $size % $sector !== 0) {
            return false;
        }
        $entries = unpack('Vfat/Vdirectory/Vsignature/Vcutoff/Vmini/VminiCount/Vdifat/VdifatCount', substr($header, 44, 32));
        $lastSector = intdiv($size, $sector) - 2;
        $pointers = intdiv($sector, 4);
        if ($entries['fat'] < 1 || $entries['fat'] > $lastSector
            || $entries['difatCount'] > max(0, (int) ceil(($entries['fat'] - 109) / ($pointers - 1)))) {
            return false;
        }
        $fat = array_values(unpack('V*', substr($header, 76)));
        $next = $entries['difat'];
        for ($i = 0; $i < $entries['difatCount']; $i++) {
            if ($next > $lastSector || fseek($stream, ($next + 1) * $sector) !== 0) {
                return false;
            }
            $part = array_values(unpack('V*', fread($stream, $sector)));
            $next = array_pop($part);
            array_push($fat, ...$part);
        }
        $fat = array_slice($fat, 0, $entries['fat']);
        $dir = $entries['directory'];
        $seen = [];
        $names = $extension === 'doc' ? ['WordDocument'] : ['Workbook', 'Book'];
        while ($dir <= $lastSector && ! isset($seen[$dir])) {
            if (fseek($stream, ($dir + 1) * $sector) !== 0 || strlen($directory = fread($stream, $sector)) !== $sector) {
                return false;
            }
            if ($seen === [] && ($directory[66] !== "\x05"
                || substr($directory, 0, 22) !== mb_convert_encoding("Root Entry\0", 'UTF-16LE', 'UTF-8'))) {
                return false;
            }
            foreach (str_split($directory, 128) as $entry) {
                $length = unpack('vlength', substr($entry, 64, 2))['length'];
                if ($entry[66] === "\x02" && $length >= 2 && $length <= 64 && $length % 2 === 0
                    && in_array(mb_convert_encoding(substr($entry, 0, $length - 2), 'UTF-8', 'UTF-16LE'), $names, true)
                    && unpack('Vsize', substr($entry, 120, 4))['size'] > 0) {
                    return true;
                }
            }
            $seen[$dir] = true;
            $table = $fat[intdiv($dir, $pointers)] ?? 0xFFFFFFFF;
            if ($table > $lastSector || fseek($stream, ($table + 1) * $sector + ($dir % $pointers) * 4) !== 0) {
                return false;
            }
            $dir = unpack('Vnext', fread($stream, 4))['next'];
        }

        return false;
    }
}
