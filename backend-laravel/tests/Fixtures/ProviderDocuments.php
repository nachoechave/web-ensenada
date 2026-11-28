<?php

namespace Tests\Fixtures;

use ZipArchive;

final class ProviderDocuments
{
    public const ADMIN = '/api/admin/proveedores/documentos';

    public const PUBLIC = '/api/proveedores/documentos';

    public static function upload(string $bytes, string $name = 'Documento.pdf', string $title = 'Documento sintético'): array
    {
        return ['multipart' => [
            ['name' => 'titulo', 'contents' => $title],
            ['name' => 'descripcion', 'contents' => ' Descripción sintética '],
            ['name' => 'archivo', 'contents' => $bytes, 'filename' => $name, 'headers' => ['Content-Type' => 'application/octet-stream']],
        ]];
    }

    public static function xlsx(bool $workbook = true): string
    {
        $path = tempnam(sys_get_temp_dir(), 'provider-zip');
        try {
            $zip = new ZipArchive;
            $zip->open($path, ZipArchive::OVERWRITE);
            $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/></Types>');
            if ($workbook) {
                $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>');
            }
            $zip->close();

            return file_get_contents($path);
        } finally {
            unlink($path);
        }
    }

    public static function compound(string $extension = 'doc'): string
    {
        $magic = "\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1";
        $header = $magic.str_repeat("\0", 16).pack('v5', 62, 3, 65534, 9, 6).str_repeat("\0", 6)
            .pack('V10', 0, 1, 1, 0, 4096, 0xFFFFFFFE, 0, 0xFFFFFFFE, 0, 0);
        $header = str_pad($header, 512, "\xff");
        $fat = pack('V10', 0xFFFFFFFD, 0xFFFFFFFE, 3, 4, 5, 6, 7, 8, 9, 0xFFFFFFFE);
        $fat = str_pad($fat, 512, "\xff");
        $root = str_pad(mb_convert_encoding("Root Entry\0", 'UTF-16LE', 'UTF-8'), 64, "\0").pack('vCC', 22, 5, 1)
            .pack('V3', 0xFFFFFFFF, 0xFFFFFFFF, 1).str_repeat("\0", 36).pack('V3', 0xFFFFFFFE, 0, 0);
        $name = mb_convert_encoding(($extension === 'doc' ? 'WordDocument' : 'Workbook')."\0", 'UTF-16LE', 'UTF-8');
        $stream = str_pad($name, 64, "\0").pack('vCC', strlen($name), 2, 1)
            .pack('V3', 0xFFFFFFFF, 0xFFFFFFFF, 0xFFFFFFFF).str_repeat("\0", 36).pack('V3', 2, 4096, 0);

        return $header.$fat.str_pad($root.$stream, 512, "\0").str_repeat("\0", 4096);
    }
}
