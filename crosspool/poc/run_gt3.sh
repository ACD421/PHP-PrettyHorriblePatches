#!/bin/bash
# E1 exec-family first-call map per tip: B warms, A fires each family member once.
# usage: bash run_gt3.sh [php-tag]   (default 8.4)
set -u
TAG=${1:-8.4}
H="$(cd "$(dirname "$0")" && pwd)"
PORT_A=${PORT_A:-19001}
PORT_B=${PORT_B:-19002}

docker rm -f g3 >/dev/null 2>&1
docker run -d --name g3 -p ${PORT_A}:9001 -p ${PORT_B}:9002 \
  -v "$H/www":/www -v "$H/conf":/cconf:ro \
  php:${TAG}-fpm php-fpm -F -y /cconf/fpm.conf >/dev/null || { echo "RUN FAIL"; exit 1; }
ok=""
for i in $(seq 1 20); do
  out=$(python3 "$H/fcgi_client.py" 127.0.0.1 ${PORT_B} /www/ping.php 2>/dev/null)
  echo "$out" | grep -q pong && { ok=1; break; }
  sleep 1
done
[ -z "$ok" ] && { echo "UNREACHABLE"; docker rm -f g3 >/dev/null; exit 1; }

for s in exec shell system passthru; do
  echo "--- [$s] B warm:"
  python3 "$H/fcgi_client.py" 127.0.0.1 ${PORT_B} /www/gt_$s.php 2>/dev/null | sed -n '/^Content-type/,$p' | tail -n +2
  docker exec g3 rm -f /www/mk_$s.log
  echo "--- [$s] A fire (marker cleared):"
  python3 "$H/fcgi_client.py" 127.0.0.1 ${PORT_A} /www/gt_$s.php 2>/dev/null | sed -n '/^Content-type/,$p' | tail -n +2
  if docker exec g3 test -f /www/mk_$s.log; then echo "--- [$s] RAN IN DISABLED POOL"; else echo "--- [$s] no marker"; fi
done
docker rm -f g3 >/dev/null 2>&1
