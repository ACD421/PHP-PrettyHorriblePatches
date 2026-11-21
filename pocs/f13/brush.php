<?php
// imagesetbrush stores the raw gdImage*. Destroying the brush object frees it.
// The next brushed draw reads that pointer.
ini_set('memory_limit', '512M');
$im = imagecreatetruecolor(8, 8);
$br = imagecreatetruecolor(40000, 2);
imagesetbrush($im, $br);
unset($br);
gc_collect_cycles();
echo "draw\n";
imageline($im, 0, 0, 7, 7, IMG_COLOR_BRUSHED);
echo "NOCRASH\n";
