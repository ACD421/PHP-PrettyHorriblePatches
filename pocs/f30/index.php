<?php
header('Content-Type: text/plain; charset=UTF-8');
$op = $_GET['op'] ?? 'probe';
$secret = 'LEAKED_SECRET_XYZ';

function plant_request_state(string $secret): void {
    putenv('BAILOUT_LEAK=' . $secret);
    ini_set('user_agent', $secret);
    ini_set('error_prepend_string', $secret);
    ini_set('include_path', '/tmp/' . $secret);
    @output_add_rewrite_var('leak', $secret);
    $GLOBALS['secret_global'] = $secret;
    if (!function_exists('secret_func')) {
        eval('function secret_func() { return "LEAKED_SECRET_XYZ"; }');
    }
    if (!class_exists('SecretClass', false)) {
        eval('class SecretClass {}');
    }
    if (!defined('SECRET_CONST')) {
        define('SECRET_CONST', $secret);
    }
}

function plant_shutdown_state(): void {
    putenv('BAILOUT_SHUTDOWN=LEAKED_SECRET_XYZ');
    ini_set('docref_root', 'LEAKED_SECRET_XYZ');
    eval('function shutdown_defined_fn(){return "LEAKED_SECRET_XYZ";}');
}

if ($op === 'probe') {
    echo 'UA=' . ini_get('user_agent') . "\n";
    echo 'PREPEND=' . ini_get('error_prepend_string') . "\n";
    echo 'INCLUDE_PATH=' . ini_get('include_path') . "\n";
    echo 'DOCREF=' . ini_get('docref_root') . "\n";
    echo 'ENV=' . (getenv('BAILOUT_LEAK') ?: '') . "\n";
    echo 'ENV2=' . (getenv('BAILOUT_SHUTDOWN') ?: '') . "\n";
    echo 'FN=' . (function_exists('shutdown_defined_fn') ? 'yes' : 'no') . "\n";
    echo 'FN2=' . (function_exists('secret_func') ? 'yes' : 'no') . "\n";
    echo 'CLASS=' . (class_exists('SecretClass', false) ? 'yes' : 'no') . "\n";
    echo 'CONST=' . (defined('SECRET_CONST') ? SECRET_CONST : 'no') . "\n";
    echo 'OB=' . ob_get_level() . "\n";
    echo 'ERR=' . json_encode(error_get_last()) . "\n";
    echo 'MET=' . ini_get('max_execution_time') . "\n";
    echo 'MIT=' . ini_get('max_input_time') . "\n";
    echo 'GLOBAL=' . ($GLOBALS['secret_global'] ?? 'no') . "\n";
    spl_autoload_register(static function ($class) {
        eval('class ' . $class . ' {}');
    });
    echo 'AUTO=' . get_class(new ProbeFoo) . "\n";
    trigger_error('ProbeWarn', E_USER_WARNING);
    exit;
}

plant_request_state($secret);
register_shutdown_function('plant_shutdown_state');

if ($op === 'timeout_autoload') {
    set_time_limit(1);
    spl_autoload_register(static function ($class) {
        $end = microtime(true) + 3.0;
        while (microtime(true) < $end) {
        }
        eval('class ' . $class . ' {}');
    });
    echo get_class(new TimeoutAutoloadClass);
    exit;
}

if ($op === 'compile_bailout') {
    set_error_handler(static function () {
        trigger_error('compile-fatal-while-in-compilation', E_USER_ERROR);
    });
    eval('for ($i = 0; $i < 1; $i++) { $a = "${b}"; }');
    echo "COMPILE_RETURNED\n";
    exit;
}

if ($op === 'output_handler') {
    set_time_limit(1);
    ob_start(static function ($buffer) {
        $end = microtime(true) + 3.0;
        while (microtime(true) < $end) {
        }
        return $buffer;
    });
    echo 'trigger-output-handler';
    exit;
}

if ($op === 'shutdown_oom') {
    ini_set('memory_limit', '16M');
    register_shutdown_function(static function () {
        $a = str_repeat('a', 10 * 1024 * 1024);
        $GLOBALS['b'] = $a . $a;
    });
    echo "shutdown-armed\n";
    exit;
}

echo "unknown-op\n";
