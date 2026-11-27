<?php

namespace App\News;

use App\Content\SiteImageEncoder;
use App\Images\LocalImageStore;

final class NewsImageStore extends LocalImageStore
{
    public const DIRECTORY = '/var/lib/ensenada-news-storage/noticias';

    public const FILENAME = '[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.(?:jpg|png|webp)';

    public const MAX_BYTES = SiteImageEncoder::MAX_OUTPUT_BYTES;

    protected const EXTENSIONS = ['jpg', 'png', 'webp'];
}
