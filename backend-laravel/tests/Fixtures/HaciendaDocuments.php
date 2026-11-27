<?php

namespace Tests\Fixtures;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

final class HaciendaDocuments
{
    public static function pdf(int $size = 0): string
    {
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 300] >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 4\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n$xref\n";
        $end = "%%EOF\n";

        return str_pad($pdf, max(strlen($pdf), $size - strlen($end)), "\n").$end;
    }

    public static function options(string $content, string $name = 'Sintético.pdf', array $headers = []): array
    {
        return ['headers' => $headers, 'multipart' => [[
            'name' => 'archivo', 'contents' => $content, 'filename' => $name,
            'headers' => ['Content-Type' => 'application/octet-stream'],
        ]]];
    }

    public static function upload(Client $client, int $id, string $content, string $name = 'Sintético.pdf', array $headers = []): ResponseInterface
    {
        return $client->post(HaciendaFixtures::API.'/'.$id.'/archivos', self::options($content, $name, $headers));
    }
}
