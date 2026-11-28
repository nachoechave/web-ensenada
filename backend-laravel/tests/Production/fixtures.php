<?php

$image = imagecreatetruecolor(4000, 2250);
imagefilledrectangle($image, 0, 0, 3999, 2249, imagecolorallocate($image, 60, 100, 160));
imagejpeg($image, '/results/original-4000.jpg', 95);
imagepng($image, '/results/original.png');
imagedestroy($image);
mkdir('/tmp/fixture-policy', 0700);
copy('/source/docker/site-image-policy.xml', '/tmp/fixture-policy/policy.xml');
putenv('MAGICK_CONFIGURE_PATH=/tmp/fixture-policy');
exec('/usr/bin/convert /results/original.png /results/original.webp', $output, $code);
if ($code !== 0) {
    exit(1);
}
