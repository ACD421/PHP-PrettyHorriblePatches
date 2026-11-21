<?php
echo "PHP ".PHP_VERSION."\n";
echo "locale=".setlocale(LC_CTYPE, "0")."\n";
echo "MB_CUR_MAX=".ini_get("error_reporting")." mb=". (function_exists('mb_internal_encoding')?'mb':'no')."\n";

function hex($s){ return bin2hex($s)." [".$s."]\n"; }

echo "=== escapeshell default ===\n";
$samples = [
  "plain",
  "a'b",
  "a;id",
  "a\nid",
  "a\rid",
  "\xC0'",
  "\xC0';id;'",
  "héllo",
  "\xFF;id",
  "\x80';id",
  "\xC3",
  "\xC3;id",
  "'\xFF'",
  "\x81'; id; '",
];
foreach ($samples as $s) {
  echo "IN ".bin2hex($s)."\n";
  echo "ARG ".bin2hex(@escapeshellarg($s))."\n";
  echo "CMD ".bin2hex(@escapeshellcmd($s))."\n";
}

echo "=== sequential state ===\n";
$poison = ["\xC3", "\xE2\x80", "\xF0\x9F", "\x81", "\xC0", str_repeat("\xC3", 3)];
foreach ($poison as $p) {
  $a = @escapeshellarg($p);
  $b = @escapeshellarg("';id;#");
  $c = @escapeshellcmd("';id");
  echo "poison ".bin2hex($p)." arg1=".bin2hex($a)." arg2=".bin2hex($b)." cmd=".bin2hex($c)."\n";
}

echo "=== locales ===\n";
$locs = ['C','C.UTF-8','POSIX','en_US.UTF-8','en_US.utf8','zh_CN.GBK','zh_CN.gbk','ja_JP.SJIS','ja_JP.UTF-8','C.GBK'];
foreach ($locs as $loc) {
  $r = @setlocale(LC_CTYPE, $loc);
  if ($r === false) { echo "NO $loc\n"; continue; }
  $payload = "\x81'; id; '";
  $arg = @escapeshellarg($payload);
  $cmd = @escapeshellcmd("echo \x81'; id");
  echo "LOC $r ARG ".bin2hex($arg)." CMD ".bin2hex($cmd)."\n";
  // try actual injection if quotes look broken
  $out = [];
  $ec = 0;
  @exec("echo MARK ". $arg, $out, $ec);
  echo "EXEC ec=$ec out=".implode("|",$out)."\n";
}
setlocale(LC_ALL, 'C');

echo "=== proc_open nul ===\n";
$spec = [1=>['pipe','w'], 2=>['pipe','w']];
$cwd1 = "/tmp";
$cwd2 = "/tmp\0/does-not-matter";
foreach (['plain'=>$cwd1, 'nul'=>$cwd2] as $name=>$cwd) {
  $p = [];
  $proc = @proc_open(['/bin/pwd'], $spec, $p, $cwd);
  if (!is_resource($proc)) { echo "$name FAIL\n"; continue; }
  $o = stream_get_contents($p[1]);
  $e = stream_get_contents($p[2]);
  fclose($p[1]); fclose($p[2]);
  $rc = proc_close($proc);
  echo "$name rc=$rc pwd=[".trim($o)."] err=[".trim($e)."] cwdlen=".strlen($cwd)."\n";
}

echo "=== proc_open cmd nul ===\n";
$p=[];
$proc=@proc_open("echo BEFORE\0; echo AFTER", $spec, $p);
if (is_resource($proc)) {
  $o=stream_get_contents($p[1]); $e=stream_get_contents($p[2]);
  fclose($p[1]); fclose($p[2]); proc_close($proc);
  echo "STRO ".json_encode($o)." E ".json_encode($e)."\n";
} else echo "cmd nul fail\n";

echo "=== env nul ===\n";
$p=[];
$proc=@proc_open(['/usr/bin/env'], $spec, $p, null, ["FOO"=>"bar\0INJECTED=1", "OK"=>"yes"]);
if (is_resource($proc)) {
  $o=stream_get_contents($p[1]); fclose($p[1]); fclose($p[2]); proc_close($proc);
  echo $o;
}

echo "=== file descriptor nul ===\n";
@file_put_contents('/tmp/fp_real', "REALDATA\n");
@file_put_contents('/tmp/fp_real.jpg', "JPGDATA\n");
$p=[];
$proc=@proc_open(['/bin/cat'], [0=>['file', "/tmp/fp_real\0.jpg", 'r'], 1=>['pipe','w'], 2=>['pipe','w']], $p);
if (is_resource($proc)) {
  $o=stream_get_contents($p[1]); $e=stream_get_contents($p[2]);
  fclose($p[1]); fclose($p[2]); proc_close($proc);
  echo "CAT ".json_encode($o)." ERR ".json_encode($e)."\n";
} else echo "fd nul open fail\n";

echo "=== mail ===\n";
function trymail($label, $to, $sub, $msg, $hdr) {
  echo "-- $label --\n";
  $ok = @mail($to, $sub, $msg, $hdr);
  echo "ret=".var_export($ok,true)."\n";
}
trymail('basic', 'a@b.c', 'sub', 'body', 'From: a@b.c');
trymail('inj', 'a@b.c', 'sub', 'body', "From: a@b.c\r\n\r\nINJECTED-BODY");
trymail('lf', 'a@b.c', 'sub', 'body', "From: a@b.c\n\nINJECTED");
trymail('barecr', 'a@b.c', 'sub', 'body', "From: a@b.c\rBcc: x@y.z");
trymail('crlfsp', 'a@b.c', 'sub', 'body', "From: a@b.c\r\n Bcc: x@y.z");
trymail('toinj', "a@b.c\r\nBcc: x@y.z", 'sub', 'body', null);
trymail('tofold', "a@b.c\r\n Bcc: x@y.z", 'sub', 'body', null);
trymail('subinj', 'a@b.c', "sub\r\nBcc: x@y.z", 'body', null);
trymail('extra', 'a@b.c', 'sub', 'bodyline', 'From: a@b.c');

echo "=== nul paths ===\n";
try { var_dump(@file_get_contents("/etc/passwd\0")); } catch (Throwable $e) { echo "fgc ".$e->getMessage()."\n"; }
try { var_dump(@fopen("/etc/passwd\0.jpg", 'r')); } catch (Throwable $e) { echo "fopen ".$e->getMessage()."\n"; }

echo "DONE\n";
