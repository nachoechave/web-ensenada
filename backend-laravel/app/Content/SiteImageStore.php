<?php

namespace App\Content;

use App\Images\LocalImageStore;

final class SiteImageStore extends LocalImageStore
{
    public const DIRECTORY = '/var/lib/ensenada-site-storage/sitio';

    public const FILENAME = '[a-zA-Z0-9_-][a-zA-Z0-9._-]{0,245}\.(?i:jpg|jpeg|png|webp)';

    public const MAX_BYTES = SiteImageEncoder::MAX_INPUT_BYTES;

    protected const EXTENSIONS = ['jpg', 'png', 'webp'];

    public function open(string $filename): ?array
    {
        $opened = parent::open($filename);
        if ($opened === null) {
            return null;
        }
        [$stream, $size] = $opened;
        $header = fread($stream, 12) ?: '';
        $valid = match (self::mime(pathinfo($filename, PATHINFO_EXTENSION))) {
            'image/png' => str_starts_with($header, "\x89PNG\r\n\x1a\n"),
            'image/jpeg' => str_starts_with($header, "\xff\xd8\xff"),
            'image/webp' => strlen($header) === 12 && str_starts_with($header, 'RIFF')
                && substr($header, 8) === 'WEBP' && unpack('V', $header, 4)[1] + 8 === $size,
        };
        if (! $valid || ! rewind($stream)) {
            fclose($stream);

            return null;
        }

        return $opened;
    }
}
