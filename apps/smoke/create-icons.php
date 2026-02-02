<?php

// Create 192x192 icon
$img192 = imagecreatetruecolor(192, 192);
$blue = imagecolorallocate($img192, 13, 110, 253);
$white = imagecolorallocate($img192, 255, 255, 255);
imagefill($img192, 0, 0, $blue);
$text = 'A';
$fontFile = 'C:/Windows/Fonts/arialbd.ttf';
if (!file_exists($fontFile)) {
    $fontFile = 'C:/Windows/Fonts/arial.ttf';
}
imagettftext($img192, 120, 0, 50, 150, $white, $fontFile, $text);
imagepng($img192, 'public/icon-192.png');
imagedestroy($img192);

// Create 512x512 icon
$img512 = imagecreatetruecolor(512, 512);
$blue = imagecolorallocate($img512, 13, 110, 253);
$white = imagecolorallocate($img512, 255, 255, 255);
imagefill($img512, 0, 0, $blue);
imagettftext($img512, 320, 0, 130, 400, $white, $fontFile, $text);
imagepng($img512, 'public/icon-512.png');
imagedestroy($img512);

echo "Icons created: public/icon-192.png and public/icon-512.png\n";
