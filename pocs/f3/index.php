<?php
/**
 * Lab target for CVE-2026-115560.
 * Server ini is session.serialize_handler=php_serialize (or php_binary).
 * Upload progress runs before this script, under that handler.
 * The switch to php must happen before any output and before session_start().
 */
ini_set('display_errors', '1');
ini_set('html_errors', '0');
error_reporting(E_ALL);

$start_handler = ini_get('session.serialize_handler');
$enabled = ini_get('session.upload_progress.enabled');
$cleanup = ini_get('session.upload_progress.cleanup');
$switched = ini_set('session.serialize_handler', 'php');
$runtime_handler = ini_get('session.serialize_handler');

class Demo
{
    public $cmd;
    public function __wakeup()
    {
        echo "WAKEUP:" . $this->cmd . "\n";
    }
    public function __destruct()
    {
        echo "DTOR:" . $this->cmd . "\n";
    }
}

$save = (string) ini_get('session.save_path');
if ($save === '') {
    $save = sys_get_temp_dir();
}
$parts = explode(';', $save);
$save = rtrim((string) end($parts), "/\\");
$sid = $_COOKIE['PHPSESSID'] ?? '';
$file = $save . DIRECTORY_SEPARATOR . 'sess_' . $sid;
$raw = ($sid !== '' && is_file($file)) ? file_get_contents($file) : '';

session_start();

echo "PHP:" . PHP_VERSION . "\n";
echo "START_HANDLER:" . $start_handler . "\n";
echo "RUNTIME_HANDLER:" . $runtime_handler . "\n";
echo "INI_SET:" . var_export($switched, true) . "\n";
echo "UPLOAD_PROGRESS:" . $enabled . "\n";
echo "CLEANUP:" . $cleanup . "\n";
echo "SID:" . $sid . "\n";
echo "SESS_RAW:" . $raw . "\n";
echo "SESS_HAS_PIPE:" . ($raw !== '' && strpos($raw, '|O:4:"Demo"') !== false ? 'yes' : 'no') . "\n";
echo "AFTER_STATUS:" . session_status() . "\n";
echo "AFTER_COUNT:" . (isset($_SESSION) && is_array($_SESSION) ? count($_SESSION) : -1) . "\n";
echo "DONE\n";
