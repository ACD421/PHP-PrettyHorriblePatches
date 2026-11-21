<?php
ini_set('display_errors', '1');
ini_set('html_errors', '0');
error_reporting(E_ALL);
session_start();
if (isset($_GET['set'])) {
    $_SESSION['secret'] = 'token';
}
$keys = (isset($_SESSION) && is_array($_SESSION)) ? implode(',', array_keys($_SESSION)) : '';
echo "PHP:" . PHP_VERSION . "\n";
echo "HANDLER:" . ini_get('session.serialize_handler') . "\n";
echo "SECRET:" . ($_SESSION['secret'] ?? 'missing') . "\n";
echo "KEYS:" . $keys . "\n";
echo "ID:" . session_id() . "\n";
