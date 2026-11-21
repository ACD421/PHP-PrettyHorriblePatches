#!/bin/bash
# E1 RCE matrix: dual-pool fpm, marker discipline. Runs from this directory.
# usage: bash run_multi1.sh [php-tag]   (default 8.4; expects ./conf and ./www)
set -u
TAG=${1:-8.4}
H="$(cd "$(dirname "$0")" && pwd)"
PORT_A=${PORT_A:-19001}
PORT_B=${PORT_B:-19002}

start_docker() {
  docker rm -f c2m >/dev/null 2>&1
  docker run -d --name c2m -p ${PORT_A}:9001 -p ${PORT_B}:9002 \
    -v "$H/www":/www -v "$H/conf":/cconf:ro \
    php:${TAG}-fpm php-fpm -F -y /cconf/fpm-multi1.conf >/dev/null
}
fireA() { python3 "$H/fcgi_client.py" 127.0.0.1 ${PORT_A} "$1" 2>/dev/null | sed -n '/^Content-type/,$p' | tail -n +2; }
fireB() { python3 "$H/fcgi_client.py" 127.0.0.1 ${PORT_B} "$1" 2>/dev/null | sed -n '/^Content-type/,$p' | tail -n +2; }

start_docker
ok=""
for i in $(seq 1 20); do
  out=$(fireB /www/ping.php)
  echo "$out" | grep -q pong && { ok=1; break; }
  sleep 1
done
[ -z "$ok" ] && { echo "poolB UNREACHABLE"; docker logs c2m 2>&1 | tail -10; exit 1; }

fireB /www/gt_exec.php >/dev/null 2>&1          # pool B compiles
docker exec c2m rm -f /www/mk_exec.log           # marker deleted
fireA /www/gt_exec.php >/dev/null 2>&1           # pool A (disable_functions) fires
if docker exec c2m test -f /www/mk_exec.log; then
  echo "docker $TAG: RCE - marker written by disabled pool A"
else
  echo "docker $TAG: no marker"
fi
docker rm -f c2m >/dev/null 2>&1
