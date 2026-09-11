#!/bin/bash
set -euo pipefail
image=$1
suffix="$$-$(date +%s)"
network="snipeit-qa-$suffix"
db="snipeit-qa-db-$suffix"
app="snipeit-qa-app-$suffix"
failed="snipeit-qa-failed-$suffix"
cleanup() {
    docker rm -fv "$app" "$failed" "$db" >/dev/null 2>&1 || true
    docker network rm "$network" >/dev/null 2>&1 || true
}
trap cleanup EXIT
docker network create --internal "$network" >/dev/null
docker run -d --name "$db" --network "$network" -e MARIADB_ROOT_PASSWORD=synthetic-image-tests-only -e MARIADB_DATABASE=snipeit_image_test mariadb:11.4 >/dev/null
ready=false
for attempt in {1..60}; do
    if docker exec "$db" healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1; then ready=true; break; fi
    sleep 2
done
test "$ready" = true
common=(--network "$network" -e APP_ENV=production -e APP_DEBUG=false -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= -e APP_URL=http://localhost -e APP_TIMEZONE=America/Sao_Paulo -e APP_LOCALE=pt-BR -e DB_CONNECTION=mysql -e "DB_HOST=$db" -e DB_PORT=3306 -e DB_DATABASE=snipeit_image_test -e DB_PASSWORD=synthetic-image-tests-only -e MAIL_MAILER=log -e SESSION_DRIVER=file -e CACHE_DRIVER=file -e QUEUE_DRIVER=sync)
# A real startup with rejected credentials must terminate, never serve HTTP.
docker run -d --name "$failed" "${common[@]}" -e DB_USERNAME=invalid_qa_user "$image" >/dev/null
stopped=false
for attempt in {1..45}; do
    if [ "$(docker inspect "$failed" --format '{{.State.Running}}')" = false ]; then stopped=true; break; fi
    sleep 2
done
test "$stopped" = true
test "$(docker inspect "$failed" --format '{{.State.ExitCode}}')" != 0
docker run -d --name "$app" "${common[@]}" -e DB_USERNAME=root "$image" >/dev/null
ready=false
for attempt in {1..90}; do
    if docker exec "$app" curl -fsS -o /dev/null http://127.0.0.1/login; then ready=true; break; fi
    if [ "$(docker inspect "$app" --format '{{.State.Running}}')" = false ]; then break; fi
    sleep 2
done
if [ "$ready" != true ]; then docker logs --tail 80 "$app"; exit 1; fi
docker exec -i -w /var/www/html "$app" php < scripts/qa/image-fixture.php
test "$(docker exec "$app" curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1/login)" = 200
docker exec -w /var/www/html "$app" php artisan migrate:status --no-ansi
docker exec -w /var/www/html "$app" php artisan route:list --path=contracts --except-vendor --no-ansi
docker exec -w /var/www/html "$app" php artisan schedule:list --no-ansi
docker exec -u docker "$app" sh -c 'for dir in contracts contract_amendments contract_installments; do test -w "/var/www/html/storage/private_uploads/$dir" || exit 1; done'
docker exec "$app" sh -c 'test -f /var/www/html/vendor/autoload.php && test -s /var/www/html/public/js/dist/all.js && test ! -e /var/www/html/.local-production && test ! -e /var/www/html/.local-validation'
echo 'Image smoke passed: rejected DB fails startup; synthetic migration, HTTP, routes, scheduler and upload permissions verified.'
