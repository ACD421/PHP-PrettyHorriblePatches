<?php
/**
 * DNS wildcard in a peer certificate is accepted as an IP address and as
 * every single-label hostname. Loopback only.
 */
error_reporting(E_ALL);

$dir = sys_get_temp_dir() . '/tls-wild-' . getmypid();
mkdir($dir, 0700, true);

function cnf_write(string $path, string $extra): void {
    file_put_contents($path, <<<CFG
[ req ]
distinguished_name = dn
default_md = sha256
prompt = no
[ dn ]
countryName = US
organizationName = Probe
commonName = placeholder
[ v3_ca ]
basicConstraints = critical,CA:TRUE
keyUsage = keyCertSign, cRLSign
[ usr_cert ]
basicConstraints = CA:FALSE
keyUsage = digitalSignature, keyEncipherment
extendedKeyUsage = serverAuth
$extra
CFG);
}

function new_key() {
    $k = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    if ($k === false) {
        fwrite(STDERR, "pkey: ".openssl_error_string()."\n");
        exit(1);
    }
    return $k;
}

$caCnf = $dir.'/ca.cnf';
cnf_write($caCnf, '');
$caKey = new_key();
$caCsr = openssl_csr_new(['commonName' => 'Probe CA', 'organizationName' => 'Probe'], $caKey, [
    'config' => $caCnf, 'digest_alg' => 'sha256', 'x509_extensions' => 'v3_ca',
]);
$ca = openssl_csr_sign($caCsr, null, $caKey, 2, [
    'config' => $caCnf, 'digest_alg' => 'sha256', 'x509_extensions' => 'v3_ca',
]);
if ($ca === false) {
    fwrite(STDERR, "ca sign: ".openssl_error_string()."\n");
    exit(1);
}
$caFile = $dir.'/ca.pem';
openssl_x509_export_to_file($ca, $caFile);

function leaf_pem(string $dir, string $caFile, $ca, $caKey, string $cn, string $san): string {
    $cnf = $dir.'/leaf-'.md5($san.$cn).'.cnf';
    cnf_write($cnf, "subjectAltName = $san\n");
    $key = new_key();
    $dn = ['organizationName' => 'Probe', 'commonName' => $cn];
    $opts = ['config' => $cnf, 'digest_alg' => 'sha256', 'req_extensions' => 'usr_cert', 'x509_extensions' => 'usr_cert'];
    $csr = openssl_csr_new($dn, $key, $opts);
    $cert = openssl_csr_sign($csr, $ca, $caKey, 2, $opts);
    if ($cert === false) {
        fwrite(STDERR, "leaf sign ($san): ".openssl_error_string()."\n");
        exit(1);
    }
    $pem = $dir.'/leaf-'.md5($san.$cn).'.pem';
    $certTxt = '';
    $keyTxt = '';
    openssl_x509_export($cert, $certTxt);
    openssl_pkey_export($key, $keyTxt);
    file_put_contents($pem, $certTxt.$keyTxt);
    $parsed = openssl_x509_parse($cert);
    echo "CERT cn=".($parsed['subject']['CN'] ?? '?')." san=".($parsed['extensions']['subjectAltName'] ?? 'none')."\n";
    return $pem;
}

function try_connect(string $pem, string $caFile, string $targetHost, ?string $peerName = null): string {
    $port = null;
    for ($i = 0; $i < 40; $i++) {
        $p = 25000 + (($i * 131 + getmypid()) % 15000);
        $probe = @stream_socket_server("tcp://127.0.0.1:$p", $errno, $errstr);
        if ($probe) {
            fclose($probe);
            $port = $p;
            break;
        }
    }
    if ($port === null) {
        return "NOPORT";
    }
    $srvPhp = dirname($pem).'/srv.php';
    if (!is_file($srvPhp)) {
        file_put_contents($srvPhp, <<<'PHP'
<?php
$pem = $argv[1];
$port = (int)$argv[2];
$ctx = stream_context_create(['ssl' => ['local_cert' => $pem]]);
$srv = stream_socket_server("ssl://127.0.0.1:$port", $errno, $errstr, STREAM_SERVER_BIND|STREAM_SERVER_LISTEN, $ctx);
if (!$srv) { fwrite(STDERR, "bind $errstr\n"); exit(2); }
$c = @stream_socket_accept($srv, 4);
if ($c) { @fread($c, 4); fclose($c); }
PHP);
    }
    $proc = proc_open([PHP_BINARY, $srvPhp, $pem, (string)$port], [1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
    if (!is_resource($proc)) {
        return "SPAWN_FAIL";
    }
    usleep(150000);
    $ssl = [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'cafile' => $caFile,
        'verify_depth' => 5,
    ];
    if ($peerName !== null) {
        $ssl['peer_name'] = $peerName;
    }
    $ctx = stream_context_create(['ssl' => $ssl]);
    $errno = 0; $errstr = '';
    $client = @stream_socket_client("ssl://{$targetHost}:{$port}", $errno, $errstr, 3, STREAM_CLIENT_CONNECT, $ctx);
    $ok = is_resource($client);
    if ($ok) {
        fwrite($client, "hi");
        fclose($client);
    }
    fclose($pipes[1]); fclose($pipes[2]);
    proc_close($proc);
    return ($ok ? "MATCH" : "REJECT")." host=$targetHost peer=".($peerName ?? '(url)')." err=".trim($errstr);
}

echo "PHP ".PHP_VERSION."\n";

$ipWild = leaf_pem($dir, $caFile, $ca, $caKey, 'other.example', 'DNS:*.0.0.1');
echo "ip-wildcard ".try_connect($ipWild, $caFile, '127.0.0.1')."\n";

$ipLit = leaf_pem($dir, $caFile, $ca, $caKey, 'other.example', 'DNS:127.0.0.1');
echo "dns-ip-literal ".try_connect($ipLit, $caFile, '127.0.0.1')."\n";

$star = leaf_pem($dir, $caFile, $ca, $caKey, 'other.example', 'DNS:*');
echo "star-localhost ".try_connect($star, $caFile, '127.0.0.1', 'localhost')."\n";

$stardot = leaf_pem($dir, $caFile, $ca, $caKey, 'other.example', 'DNS:*.');
echo "star-dot ".try_connect($stardot, $caFile, '127.0.0.1', 'localhost')."\n";

$control = leaf_pem($dir, $caFile, $ca, $caKey, 'legit.example', 'DNS:attacker.test');
echo "control-dns-san ".try_connect($control, $caFile, '127.0.0.1', 'legit.example')."\n";

$proper = leaf_pem($dir, $caFile, $ca, $caKey, 'www.example.org', 'DNS:*.example.org');
echo "proper-one-label ".try_connect($proper, $caFile, '127.0.0.1', 'www.example.org')."\n";
echo "proper-two-label ".try_connect($proper, $caFile, '127.0.0.1', 'a.b.example.org')."\n";

if (function_exists('curl_init')) {
    $secret = $dir.'/secret.txt';
    file_put_contents($secret, "SECRETDATA-CURL\n");
    $srv = <<<'PHP'
<?php
$secret = $argv[1];
$listen = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if (!$listen) { fwrite(STDERR, $errstr); exit(2); }
echo stream_socket_get_name($listen, false), "\n";
fflush(STDOUT);
foreach (['file', 'gopher', 'phar'] as $kind) {
    $c = @stream_socket_accept($listen, 3);
    if (!$c) { break; }
    fread($c, 8192);
    if ($kind === 'file') {
        $loc = 'file://'.$secret;
    } elseif ($kind === 'gopher') {
        $loc = 'gopher://127.0.0.1:1/_x';
    } else {
        $loc = 'phar://'.$secret;
    }
    fwrite($c, "HTTP/1.1 302 Found\r\nLocation: $loc\r\nContent-Length: 0\r\nConnection: close\r\n\r\n");
    fclose($c);
}
PHP;
    $srvFile = $dir.'/hs.php';
    file_put_contents($srvFile, $srv);
    $proc = proc_open([PHP_BINARY, $srvFile, $secret], [1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
    $addr = trim(fgets($pipes[1]));
    foreach (['file', 'gopher', 'phar'] as $kind) {
        $ch = curl_init('http://'.$addr.'/'.$kind);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
        ]);
        $b = curl_exec($ch);
        $leak = is_string($b) && str_contains($b, 'SECRETDATA');
        echo "follow_$kind ".($leak ? 'LEAK' : 'BLOCK')." err=".curl_error($ch)." redir=".curl_getinfo($ch, CURLINFO_REDIRECT_URL)."\n";
    }
    fclose($pipes[1]); fclose($pipes[2]);
    proc_close($proc);
}

echo "DONE\n";
