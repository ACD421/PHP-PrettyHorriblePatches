<?php
/**
 * Expects argv[1] = base dir that already contains:
 *   sess\sess_abcd  -> symlink to outside.txt
 *   outside.txt     -> valid php session payload k|s:11:"FROM_TARGET";
 */
$base = $argv[1];
$save = $base . DIRECTORY_SEPARATOR . 'sess';
$outside = $base . DIRECTORY_SEPARATOR . 'outside.txt';
ini_set('session.save_path', $save);
ini_set('session.save_handler', 'files');
ini_set('session.use_strict_mode', '0');
session_id('abcd');
session_start();
$before = $_SESSION['k'] ?? '(unset)';
$_SESSION['k'] = 'OVERWRITTEN';
session_write_close();
$out = file_get_contents($outside);
echo "PHP " . PHP_VERSION . "\n";
echo "RESULT before=$before\n";
echo "RESULT outside=$out\n";
echo "DONE exit=0\n";
