<?php

namespace App\Hacienda;

use App\Images\LocalImageStore;

final class DocumentStore extends LocalImageStore
{
    public const DIRECTORY = '/var/lib/ensenada-hacienda-storage/hacienda';

    public const FILENAME = '[a-f0-9-]{36}\.(?:pdf|jpg|png)';

    public const MAX_BYTES = 20 * 1024 * 1024;

    protected const EXTENSIONS = ['pdf', 'jpg', 'png'];

    public const MIME_TYPES = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png'];

    public const SIGNATURES = ['pdf' => '%PDF-', 'jpg' => "\xff\xd8\xff", 'png' => "\x89PNG\r\n\x1a\n"];

    public function removeUnassociatedUpload(string $filename): bool
    {
        return preg_match('~^'.self::FILENAME.'$~D', $filename) === 1
            && $this->isPrivateDirectory() && @unlink($this->directory().'/'.$filename);
    }
}
