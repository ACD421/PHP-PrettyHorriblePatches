<?php
/**
 * socket_connect() casts the port through unsigned short before htons().
 * Sibling APIs on 8.5+ reject ports outside 0..65535. connect does not.
 * A port of P+65536 connects to P. A port of -1 connects to 65535.
 */
function listen_on($port) {
    $srv = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($srv === false) {
        fwrite(STDERR, "socket_create failed\n");
        exit(2);
    }
    socket_set_option($srv, SOL_SOCKET, SO_REUSEADDR, 1);
    if (!@socket_bind($srv, '127.0.0.1', $port)) {
        fwrite(STDERR, "bind $port failed: " . socket_strerror(socket_last_error($srv)) . "\n");
        exit(2);
    }
    if (!socket_listen($srv, 1)) {
        fwrite(STDERR, "listen failed\n");
        exit(2);
    }
    socket_getsockname($srv, $addr, $bound);
    return [$srv, $bound];
}

function connect_port($port) {
    $cli = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_set_nonblock($cli);
    $ok = @socket_connect($cli, '127.0.0.1', $port);
    if ($ok) {
        return $cli;
    }
    $err = socket_last_error($cli);
    // EINPROGRESS / EALREADY are the expected non-blocking outcomes.
    if ($err !== SOCKET_EINPROGRESS && $err !== SOCKET_EALREADY && $err !== 0) {
        // Some builds report the error only via the warning path; still try select.
    }
    $w = [$cli];
    $r = null;
    $e = null;
    $n = @socket_select($r, $w, $e, 2);
    if ($n !== 1) {
        fwrite(STDERR, "select for port $port returned " . var_export($n, true) . " err=" . socket_strerror(socket_last_error($cli)) . "\n");
        return false;
    }
    return $cli;
}

echo "PHP " . PHP_VERSION . "\n";

[$srv, $bound] = listen_on(0);
$wrapped = $bound + 65536;
echo "listen_port=$bound\n";
echo "connect_arg=$wrapped\n";
$cli = connect_port($wrapped);
if ($cli === false) {
    echo "CONNECT_WRAP_FAIL\n";
    exit(1);
}
$acc = @socket_accept($srv);
if ($acc === false) {
    echo "ACCEPT_WRAP_FAIL " . socket_strerror(socket_last_error($srv)) . "\n";
    exit(1);
}
socket_getsockname($acc, $sa, $sp);
echo "accepted_port=$sp\n";
echo "truncated_connect=" . ($sp === $bound ? "yes" : "no") . "\n";
socket_close($acc);
socket_close($cli);
socket_close($srv);

// Negative port wraps to 65535.
[$srv2, $bound2] = listen_on(65535);
echo "neg_listen_port=$bound2\n";
$cli2 = connect_port(-1);
if ($cli2 === false) {
    echo "CONNECT_NEG_FAIL\n";
    exit(1);
}
$acc2 = @socket_accept($srv2);
if ($acc2 === false) {
    echo "ACCEPT_NEG_FAIL\n";
    exit(1);
}
socket_getsockname($acc2, $sa2, $sp2);
echo "neg_accepted_port=$sp2\n";
echo "neg_wrap=" . ($sp2 === 65535 ? "yes" : "no") . "\n";
socket_close($acc2);
socket_close($cli2);
socket_close($srv2);

// Sibling API: bind of an out-of-range port.
$b = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
$bind_arg = 65536 + 9;
try {
    $ok = socket_bind($b, '127.0.0.1', $bind_arg);
    if ($ok) {
        socket_getsockname($b, $ba, $bp);
        echo "bind_arg=$bind_arg bind_port=$bp bind_truncated=" . ($bp === 9 ? "yes" : "no") . "\n";
    } else {
        echo "bind_arg=$bind_arg bind_failed=" . socket_strerror(socket_last_error($b)) . "\n";
    }
} catch (Throwable $ex) {
    echo "bind_arg=$bind_arg bind_threw=" . $ex->getMessage() . "\n";
}
socket_close($b);

// UDP sendto of an out-of-range port.
$udp_srv = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
socket_bind($udp_srv, '127.0.0.1', 0);
socket_getsockname($udp_srv, $ua, $uport);
$udp_cli = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
$send_arg = $uport + 65536;
try {
    $sent = socket_sendto($udp_cli, "P", 1, 0, '127.0.0.1', $send_arg);
    echo "sendto_arg=$send_arg udp_bound=$uport sent=" . var_export($sent, true) . "\n";
    echo "sendto_accepted_oor=" . ($sent === 1 ? "yes" : "no") . "\n";
} catch (Throwable $ex) {
    echo "sendto_arg=$send_arg sendto_threw=" . $ex->getMessage() . "\n";
}
socket_close($udp_cli);
socket_close($udp_srv);

// socket_create_listen out-of-range port.
$listen_arg = 65536 + 7;
try {
    $ls = @socket_create_listen($listen_arg);
    if ($ls === false) {
        echo "create_listen_arg=$listen_arg create_listen_failed\n";
    } else {
        socket_getsockname($ls, $la, $lp);
        echo "create_listen_arg=$listen_arg create_listen_port=$lp create_listen_truncated=" . ($lp === 7 ? "yes" : "no") . "\n";
        socket_close($ls);
    }
} catch (Throwable $ex) {
    echo "create_listen_arg=$listen_arg create_listen_threw=" . $ex->getMessage() . "\n";
}

echo "DONE\n";
