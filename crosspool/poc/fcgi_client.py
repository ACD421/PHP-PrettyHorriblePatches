import socket, struct, sys

def enc_len(n):
    if n < 128:
        return bytes([n])
    return struct.pack('>I', n | 0x80000000)

def record(rtype, rid, payload):
    pad = (8 - (len(payload) % 8)) % 8
    hdr = struct.pack('>BBHHBB', 1, rtype, rid, len(payload), pad, 0)
    return hdr + payload + b'\x00' * pad

def build_params(env):
    body = b''
    for k, v in env.items():
        kb, vb = k.encode(), v.encode()
        body += enc_len(len(kb)) + enc_len(len(vb)) + kb + vb
    return body

def fcgi_request(host, port, script, query=''):
    rid = 1
    s = socket.create_connection((host, port), timeout=15)
    s.sendall(record(1, rid, struct.pack('>HBxxxxx', 1, 0)))
    env = {
        'SCRIPT_FILENAME': script,
        'SCRIPT_NAME': '/' + script.split('/')[-1],
        'DOCUMENT_ROOT': '/www',
        'REQUEST_METHOD': 'GET',
        'QUERY_STRING': query,
        'CONTENT_TYPE': '',
        'CONTENT_LENGTH': '0',
        'SERVER_PROTOCOL': 'HTTP/1.1',
        'REQUEST_URI': '/' + script.split('/')[-1] + ('?' + query if query else ''),
        'SERVER_SOFTWARE': 'verify',
        'REMOTE_ADDR': '127.0.0.1',
    }
    s.sendall(record(4, rid, build_params(env)))
    s.sendall(record(4, rid, b''))
    s.sendall(record(5, rid, b''))
    stdout, stderr = b'', b''
    s.settimeout(15)
    try:
        while True:
            data = s.recv(65536)
            if not data:
                break
            i = 0
            while i + 8 <= len(data):
                ver, rtype, prid, clen, plen, _ = struct.unpack('>BBHHBB', data[i:i+8])
                body = data[i+8:i+8+clen]
                if rtype == 6:
                    stdout += body
                elif rtype == 7:
                    stderr += body
                i += 8 + clen + plen
    except socket.timeout:
        pass
    s.close()
    return stdout, stderr

if __name__ == '__main__':
    host, port, script = sys.argv[1], int(sys.argv[2]), sys.argv[3]
    query = sys.argv[4] if len(sys.argv) > 4 else ''
    out, err = fcgi_request(host, port, script, query)
    sys.stdout.write(out.decode('utf-8', 'replace'))
    if err:
        sys.stdout.write('\n[FCGI-STDERR]\n' + err.decode('utf-8', 'replace'))
