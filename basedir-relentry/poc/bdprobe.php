<?php
echo "BASEDIR=".ini_get('open_basedir')."\n";
echo "CWD=".getcwd()."\n";
$pw = @file_get_contents('/etc/passwd');
echo "PASSWD=" . var_export($pw ? substr($pw, 0, 40) : $pw, true) . "\n";
$host = @file_get_contents('/etc/hostname');
echo "HOSTNAME=" . var_export($host, true) . "\n";
echo "END\n";
