<?php

namespace App\Proveedores;

use App\Images\LocalImageStore;

final class ProviderStore extends LocalImageStore
{
    public const DIRECTORY = '/var/lib/ensenada-proveedores-storage/proveedores';

    public const FILENAME = '[a-f0-9-]{36}\.(?:pdf|doc|xls|xlsx)';

    public const MAX_BYTES = 20 * 1024 * 1024;

    public const MIME = [
        'pdf' => 'application/pdf', 'doc' => 'application/msword', 'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    protected const EXTENSIONS = ['pdf', 'doc', 'xls', 'xlsx'];

    public function discardUpload(string $name): void
    {
        if (preg_match('~^'.self::FILENAME.'$~D', $name) && $this->isPrivateDirectory()) {
            @unlink($this->directory().'/'.$name);
        }
    }
}
