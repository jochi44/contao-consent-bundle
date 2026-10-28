#!/usr/bin/env bash
# Browser tests against a throwaway Contao install with this bundle.
#
#   tests/Browser/run.sh 5.7     Contao 5.7 on PHP 8.3
#   tests/Browser/run.sh 6.0     Contao 6.0 on PHP 8.4
#   tests/Browser/run.sh all     both, one after the other
#
# Extra arguments go to Playwright (for example a spec file or --project=chromium).
# Needs Docker, Node.js 20+ and npm. The install gets its own Docker network,
# MariaDB and Apache with PHP on 127.0.0.1:8199, and is removed at the end.
#
# KEEP=1 keeps the containers; SKIP_PW=1 runs only the HTTP checks;
# PROGRESS_FILE=<path> also writes the progress JSON there.
set -euo pipefail
shopt -s lastpipe

HERE=$(cd "$(dirname "$0")" && pwd)
ROOT=$(cd "$HERE/../.." && pwd)
VAR=$HERE/var
NET=juhe-consent-browser
DB=juhe-consent-browser-db
PHP=juhe-consent-browser-php
CACHE=juhe-consent-browser-composer
PORT=8199

CORE=${1:-}
case "$CORE" in
	all)
		shift
		status=0
		"$0" 5.7 "$@" || status=1
		"$0" 6.0 "$@" || status=1
		exit $status
		;;
	5.7) PHPV=8.3 ;;
	6.0) PHPV=8.4 ;;
	*) echo "usage: $0 5.7|6.0|all [playwright arguments]" >&2; exit 2 ;;
esac
shift
IMAGE=juhe-consent-browser-php$PHPV

mkdir -p "$VAR"
PROGRESS=$VAR/progress.json
LABEL="JUHE Consent browser tests, Contao $CORE"
START=$(($(date +%s) * 1000))
DONE=0; TOTAL=5; FAILED=0; DETAIL=""; FINISHED=false

write_progress() {
	local json
	json=$(printf '{"label":"%s","done":%s,"total":%s,"detail":"%s","failed":%s,"startedAt":%s,"finished":%s}' \
		"$LABEL" "$DONE" "$TOTAL" "${DETAIL//\"/\'}" "$FAILED" "$START" "$FINISHED")
	for file in "$PROGRESS" ${PROGRESS_FILE:+"$PROGRESS_FILE"}; do
		mkdir -p "$(dirname "$file")"
		printf '%s\n' "$json" > "$file.tmp" && mv "$file.tmp" "$file"
	done
}
step() { DONE=$1; DETAIL=$2; write_progress; echo "== $CORE: $2"; }

# Keeps the progress file fresh while a long step prints nothing.
heartbeat() {
	while sleep 15; do
		for file in "$PROGRESS" ${PROGRESS_FILE:+"$PROGRESS_FILE"}; do
			[ -f "$file" ] && cp "$file" "$file.tmp" && mv "$file.tmp" "$file"
		done
	done
}

cleanup() {
	local code=$?
	[ -n "${BEAT:-}" ] && kill "$BEAT" 2>/dev/null || true
	if [ -z "${KEEP:-}" ]; then
		docker rm -f "$DB" "$PHP" >/dev/null 2>&1 || true
		docker network rm "$NET" >/dev/null 2>&1 || true
	else
		echo "KEEP=1: containers $DB and $PHP stay up on http://127.0.0.1:$PORT"
	fi
	FINISHED=true; [ $code -ne 0 ] && FAILED=$((FAILED > 0 ? FAILED : 1)); write_progress
	exit $code
}

if docker ps -a --format '{{.Names}}' | grep -qx "$PHP"; then
	echo "$PHP already exists: another run is active or was kept (docker rm -f $DB $PHP)" >&2
	exit 1
fi
trap cleanup EXIT
heartbeat & BEAT=$!

step 0 "build PHP $PHPV image"
docker build -q --build-arg PHP_VERSION=$PHPV -t "$IMAGE" - < "$HERE/Dockerfile" >/dev/null

# Only what git would package goes into the install: tracked and new files, minus
# ignored ones and the tests folder.
STAGE=$VAR/bundle
rm -rf "$STAGE"; mkdir -p "$STAGE"
(cd "$ROOT" && git ls-files -z -c -o --exclude-standard -- . ':!tests' \
	| while IFS= read -r -d '' f; do [ -f "$f" ] && printf '%s\0' "$f"; done \
	| tar --null -T - -cf -) | tar -xf - -C "$STAGE"

step 1 "start database and web server"
docker network inspect "$NET" >/dev/null 2>&1 || docker network create "$NET" >/dev/null
PW=$(head -c 12 /dev/urandom | od -An -tx1 | tr -d ' \n')
(umask 077; printf '%s\n' "$PW" > "$VAR/db-password")
docker run -d --name "$DB" --network "$NET" --tmpfs /var/lib/mysql \
	-e MARIADB_ROOT_PASSWORD="$PW" -e MARIADB_DATABASE=contao mariadb:10.11 >/dev/null
docker run -d --name "$PHP" --network "$NET" -p 127.0.0.1:$PORT:80 \
	-e DATABASE_URL="mysql://root:$PW@$DB:3306/contao?serverVersion=10.11.0-MariaDB" \
	-e APP_SECRET="$PW" -e APP_ENV=prod -e COMPOSER_ALLOW_SUPERUSER=1 -e COMPOSER_CACHE_DIR=/composer-cache \
	-v "$CACHE":/composer-cache -v "$STAGE":/consent:ro "$IMAGE" >/dev/null
for _ in $(seq 1 60); do docker exec "$DB" mariadb -uroot -p"$PW" -e 'select 1' contao >/dev/null 2>&1 && break; sleep 2; done

step 2 "install Contao $CORE with the bundle"
docker exec "$PHP" sh -ec "
	cd /tmp && rm -rf me
	composer create-project --no-install --no-interaction --quiet 'contao/managed-edition:$CORE.*' me
	cp -a me/. /var/www/html/ && cd /var/www/html
	composer config platform.php $PHPV.0
	composer config repositories.consent '{\"type\":\"path\",\"url\":\"/consent\",\"options\":{\"symlink\":false}}'
	composer require --no-interaction --no-progress 'juhe-it-solutions/contao-consent-bundle:*@dev' 2>&1 | tail -3
	php -v | head -1
	composer show 2>/dev/null | grep -E '^(contao/core-bundle|juhe-it-solutions/contao-consent-bundle) ' || true
"

step 3 "migrate and seed"
docker exec "$PHP" sh -c 'cd /var/www/html && php vendor/bin/contao-console contao:migrate --no-interaction --no-backup 2>&1 | tail -3'
docker exec -i "$DB" mariadb -uroot -p"$PW" contao < "$HERE/seed.sql"
docker exec "$PHP" sh -ec '
	cd /var/www/html
	user() { php vendor/bin/contao-console contao:user:create --language=de --no-interaction "$@" >/dev/null; }
	user --username=check --name=Check --email=check@example.org --password=check-pass-123 --admin
	user --username=editor --name=Editor --email=editor@example.org --password=editor-pass-123
	user --username=auditor --name=Auditor --email=auditor@example.org --password=auditor-pass-123
	php vendor/bin/contao-console cache:clear --no-warmup >/dev/null
	chown -R www-data:www-data var public assets system files 2>/dev/null || true
'

step 4 "HTTP checks"
HTTP=0
HEADERS=$VAR/headers.txt "$HERE/http-checks.sh" || HTTP=1

PWSTATUS=0
if [ -z "${SKIP_PW:-}" ]; then
	step 5 "browser specs"
	cd "$HERE"
	if [ ! -d node_modules/@playwright/test ] || [ package-lock.json -nt node_modules/.package-lock.json ]; then npm ci --no-audit --no-fund; fi
	npx playwright install chromium firefox webkit
	set +e
	CONSENT_DB_PW=$PW npx playwright test "$@" 2>&1 | tee "$VAR/playwright-$CORE.log" | while IFS= read -r line; do
		printf '%s\n' "$line"
		if [[ $line =~ ^\[([0-9]+)/([0-9]+)\]\ (.*) ]]; then
			DONE=${BASH_REMATCH[1]}; TOTAL=${BASH_REMATCH[2]}; DETAIL=${BASH_REMATCH[3]:0:120}; write_progress
		elif [[ $line =~ ^\ +[0-9]+\)\ \[ ]]; then
			FAILED=$((FAILED + 1)); write_progress
		fi
	done
	PWSTATUS=${PIPESTATUS[0]}
	set -e
fi

docker exec "$PHP" sh -c 'grep -h "CRITICAL" /var/www/html/var/log/*.log 2>/dev/null | tail -5; true'
echo "== $CORE: HTTP checks $([ $HTTP = 0 ] && echo passed || echo FAILED), browser specs $([ -n "${SKIP_PW:-}" ] && echo skipped || ([ $PWSTATUS = 0 ] && echo passed || echo FAILED))"
[ $HTTP = 0 ] && [ $PWSTATUS = 0 ]
