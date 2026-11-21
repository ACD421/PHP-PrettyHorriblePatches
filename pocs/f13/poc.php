<?php
// gd/exif crash sweep. Images are built with pack(). memory_limit bounds alloc bombs.
ini_set('memory_limit', '256M');
error_reporting(E_ALL);
$fail = 0;

function note($s) { echo $s, "\n"; }

function try_img($label, $bin, $fn) {
    $path = __DIR__ . "/$label.bin";
    file_put_contents($path, $bin);
    note("TRY $label bytes=" . strlen($bin));
    try {
        $im = @$fn($path);
        if ($im instanceof GdImage) {
            note("  ok " . imagesx($im) . "x" . imagesy($im));
            // exercise scale / crop on whatever decoded
            foreach ([IMG_BILINEAR_FIXED, IMG_BICUBIC, IMG_NEAREST_NEIGHBOUR, IMG_WEIGHTED4] as $m) {
                if (!defined('IMG_BICUBIC') && $m === 0) { continue; }
                $s = @imagescale($im, 1, 1, $m);
                if ($s instanceof GdImage) { imagedestroy($s); }
            }
            $c = @imagecropauto($im, IMG_CROP_DEFAULT);
            if ($c instanceof GdImage) { imagedestroy($c); }
            $c = @imagecrop($im, ['x' => 0, 'y' => 0, 'width' => 1, 'height' => 1]);
            if ($c instanceof GdImage) { imagedestroy($c); }
            imagedestroy($im);
        } else {
            note("  null");
        }
    } catch (Throwable $e) {
        note("  ex " . $e->getMessage());
    }
}

// --- BMP 1x1 24-bit ---
$bmp = "BM" . pack('V', 58) . pack('vv', 0, 0) . pack('V', 54)
     . pack('V', 40) . pack('V', 1) . pack('V', 1) . pack('vv', 1, 24)
     . pack('V', 0) . pack('V', 4) . pack('VVVV', 0, 0, 0, 0)
     . pack('C*', 0, 0, 255, 0);
try_img('bmp24', $bmp, 'imagecreatefrombmp');

// BMP 4-bit odd width 1x1, 2 colors
$pal = pack('C*', 0,0,0,0, 255,255,255,0);
$bmp4 = "BM" . pack('V', 14+40+8+4) . pack('vv', 0, 0) . pack('V', 14+40+8)
      . pack('V', 40) . pack('V', 1) . pack('V', 1) . pack('vv', 1, 4)
      . pack('V', 0) . pack('V', 4) . pack('VV', 0, 0) . pack('V', 2) . pack('V', 0)
      . $pal . pack('C*', 0x10, 0, 0, 0);
try_img('bmp4odd', $bmp4, 'imagecreatefrombmp');

// BMP 8-bit RLE 2x2
$rle = pack('C*', 2, 1, 0, 2, 2, 3, 0, 0, 0, 1);
$bmpR = "BM" . pack('V', 14+40+8+strlen($rle)) . pack('vv', 0, 0) . pack('V', 14+40+8)
      . pack('V', 40) . pack('V', 2) . pack('V', 2) . pack('vv', 1, 8)
      . pack('V', 1) . pack('V', strlen($rle)) . pack('VV', 0, 0) . pack('V', 2) . pack('V', 0)
      . pack('C*', 0,0,0,0, 255,0,0,0) . $rle;
try_img('bmp8rle', $bmpR, 'imagecreatefrombmp');

// BMP height = INT_MIN style: 0x80000000 as signed height (top-down negation)
$bmpNeg = "BM" . pack('V', 58) . pack('vv', 0, 0) . pack('V', 54)
        . pack('V', 40) . pack('V', 1) . pack('V', 0x80000000) . pack('vv', 1, 24)
        . pack('V', 0) . pack('V', 4) . pack('VVVV', 0, 0, 0, 0)
        . pack('C*', 1, 2, 3, 0);
try_img('bmpneg', $bmpNeg, 'imagecreatefrombmp');

// --- GIF 1x1 ---
$gif = "GIF89a" . pack('vv', 1, 1) . pack('C', 0x80) . pack('C', 0) . pack('C', 0)
     . pack('C*', 0,0,0, 255,255,255)
     . "," . pack('vvvv', 0, 0, 1, 1) . pack('C', 0)
     . pack('C', 2) . pack('C', 2) . pack('C*', 0x4C, 0x01) . pack('C', 0)
     . ";";
try_img('gif1', $gif, 'imagecreatefromgif');

// GIF LZW self-prefix-ish: min code size 2, clear/data junk
$gifBad = "GIF89a" . pack('vv', 8, 8) . pack('C', 0xF0) . pack('C', 0) . pack('C', 0);
$gifBad .= str_repeat("\x00\x00\x00", 16);
$gifBad .= "," . pack('vvvv', 0, 0, 8, 8) . pack('C', 0);
$gifBad .= pack('C', 8); // code size > normal
$gifBad .= pack('C', 255) . str_repeat("\xff", 255) . pack('C', 0) . ";";
try_img('gifbad', $gifBad, 'imagecreatefromgif');

// --- TGA uncompressed 2x2 24-bit ---
$tga = pack('C', 0) . pack('C', 0) . pack('C', 2) . pack('v', 0) . pack('vv', 0, 0)
     . pack('vv', 0, 0) . pack('vv', 2, 2) . pack('C', 24) . pack('C', 0)
     . str_repeat("\x00\x80\xff", 4);
try_img('tga', $tga, 'imagecreatefromtga');

// TGA RLE
$tgaR = pack('C', 0) . pack('C', 0) . pack('C', 10) . pack('v', 0) . pack('vv', 0, 0)
      . pack('vv', 0, 0) . pack('vv', 4, 1) . pack('C', 24) . pack('C', 0)
      . pack('C', 0x83) . pack('C*', 1, 2, 3); // RLE flag + 4 pixels
try_img('tgarle', $tgaR, 'imagecreatefromtga');

// --- WBMP type 0, 1x1 ---
$wbmp = pack('C*', 0, 0, 1, 1, 0x80);
try_img('wbmp', $wbmp, 'imagecreatefromwbmp');

// WBMP multi-byte width with continuation then EOF
$wbmp2 = pack('C*', 0, 0, 0x80, 0x80);
try_img('wbmpcont', $wbmp2, 'imagecreatefromwbmp');

// --- XBM ---
$xbm = "#define t_width 3\n#define t_height 1\nstatic unsigned char t_bits[] = { 0x05 };\n";
try_img('xbm', $xbm, 'imagecreatefromxbm');

// XBM short overflow candidate: huge width via negative define
$xbm2 = "#define t_width -1\n#define t_height 1\nstatic unsigned char t_bits[] = { 0x01 };\n";
try_img('xbmneg', $xbm2, 'imagecreatefromxbm');

// --- GD palette 2x2 (word sx, sy, colors byte, transparent word, then 256*3 palette, then pixels) ---
$gd = pack('n', 2) . pack('n', 2) . pack('C', 1) . pack('n', 0);
$gd .= str_repeat("\xff\x00\x00", 256);
$gd .= pack('C*', 0, 0, 0, 0);
try_img('gdpal', $gd, 'imagecreatefromgd');

// GD2 truecolor raw tiny: magic gd2\0, vers 2, sx 1, sy 1, cs 64, fmt 3 (truecolor raw), ncx 1, ncy 1
// then truecolor flag byte + transparent int, then one pixel int
$gd2 = "gd2\x00" . pack('n', 2) . pack('n', 1) . pack('n', 1) . pack('n', 64) . pack('n', 3) . pack('n', 1) . pack('n', 1);
$gd2 .= pack('C', 1) . pack('N', 0xffffffff) . pack('N', 0x00ff0000);
try_img('gd2raw', $gd2, 'imagecreatefromgd2');

// --- in-memory ops on a real image ---
note("TRY ops");
$im = imagecreatetruecolor(3, 3);
$c = imagecolorallocate($im, 10, 20, 30);
imagefill($im, 0, 0, $c);
imagefilter($im, IMG_FILTER_GRAYSCALE);
imagefilter($im, IMG_FILTER_PIXELATE, 2, true);
imageflip($im, IMG_FLIP_BOTH);
$aff = @imageaffine($im, [1, 0, 0, 1, 0, 0]);
if ($aff instanceof GdImage) { imagedestroy($aff); }
$sc = @imagescale($im, 8, 2, IMG_BILINEAR_FIXED);
if ($sc instanceof GdImage) { imagedestroy($sc); }
$sc = @imagescale($im, 2, 8, IMG_BICUBIC);
if ($sc instanceof GdImage) { imagedestroy($sc); }
// same-dimension scale hits the memcpy width path
$sc = @imagescale($im, 3, 5, IMG_BILINEAR_FIXED);
if ($sc instanceof GdImage) { imagedestroy($sc); }
imagedestroy($im);

$p = imagecreate(4, 4);
$bg = imagecolorallocate($p, 255, 255, 255);
$fg = imagecolorallocate($p, 0, 0, 0);
imagefilledrectangle($p, -10, -10, 100, 100, $fg);
imagesetthickness($p, 5);
imageline($p, 0, 0, 3, 3, $fg);
$auto = @imagecropauto($p, IMG_CROP_WHITE);
if ($auto instanceof GdImage) { note("cropauto " . imagesx($auto) . "x" . imagesy($auto)); imagedestroy($auto); }
imagedestroy($p);

// --- EXIF thumbnail 1-byte get16 ---
if (function_exists('exif_read_data')) {
    $jpeg = pack('C*', 0xFF, 0xD8, 0xFF, 0x00, 0x00);
    $jpeg_off = 0x2C;
    $hdr  = "II" . pack('v', 42) . pack('V', 8);
    $ifd0 = pack('vV', 0, 0x0E);
    $ifd1 = pack('v', 2)
          . pack('vvVV', 0x0201, 4, 1, $jpeg_off)
          . pack('vvVV', 0x0202, 4, 1, strlen($jpeg))
          . pack('V', 0);
    $tif = $hdr . $ifd0 . $ifd1 . $jpeg;
    $path = __DIR__ . '/thumb.tif';
    file_put_contents($path, $tif);
    note("TRY exif thumb len=" . strlen($tif));
    $info = @exif_read_data($path, null, false, true);
    note("  exif " . (is_array($info) ? "array" : var_export($info, true)));

    // entry_length wrap: count 0x20000000, type DOUBLE (12) => 0x20000000*8 wraps uint32
    $count = 0x20000000;
    $ent = pack('vvV', 0x010E, 12, $count) . pack('V', 0x100);
    $body = "II" . pack('v', 42) . pack('V', 8)
          . pack('v', 1) . $ent . pack('V', 0)
          . str_repeat("\x41", 16);
    $p2 = __DIR__ . '/ifdwrap.tif';
    file_put_contents($p2, $body);
    note("TRY exif ifdwrap");
    $info = @exif_read_data($p2, null, false, true);
    note("  wrap " . (is_array($info) ? "array keys " . count($info) : var_export($info, true)));

    // self-next IFD: IFD at 8 points next at 8
    $loop = "II" . pack('v', 42) . pack('V', 8) . pack('v', 0) . pack('V', 8);
    $p3 = __DIR__ . '/ifdloop.tif';
    file_put_contents($p3, $loop);
    note("TRY exif ifdloop");
    $info = @exif_read_data($p3);
    note("  loop " . (is_array($info) ? "array" : var_export($info, true)));
}

note("DONE");
