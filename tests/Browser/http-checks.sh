#!/usr/bin/env bash
# Plain HTTP checks against the throwaway install of run.sh.
set -u
B=http://127.0.0.1:8199
HEADERS=${HEADERS:-$(mktemp)}
fail=0
check() { if eval "$2"; then echo "PASS $1"; else echo "FAIL $1"; fail=1; fi; }
H=$(curl -s -D /dev/stderr $B/ 2>"$HEADERS")
HDR=$(cat "$HEADERS")
check "page 200" "grep -q '^HTTP/1.1 200' <<<\"\$HDR\""
check "dialog injected" "grep -q 'id=\"juhe-consent\"' <<<\"\$H\""
check "iframe placeholder" "grep -q 'juhe-consent-placeholder' <<<\"\$H\""
check "script held back" "grep -q 'type=\"text/plain\" data-juhe-consent-group=\"marketing\"' <<<\"\$H\""
NONCE=$(grep -o 'juhe-consent.js[^>]*nonce="[^"]*"' <<<"$H" | sed 's/.*nonce="\([^"]*\)"/\1/')
check "nonce on script" "[ -n \"$NONCE\" ]"
check "nonce in CSP header" "grep -qi \"content-security-policy:.*nonce-$NONCE\" <<<\"\$HDR\""
JS=$(grep -o 'src="[^"]*juhe-consent.js[^"]*"' <<<"$H" | head -1 | sed 's/src="//;s/"$//')
CSS=$(grep -o 'href="[^"]*juhe-consent.css[^"]*"' <<<"$H" | head -1 | sed 's/href="//;s/"$//')
check "js asset 200 ($JS)" "[ \"\$(curl -s -o /dev/null -w '%{http_code}' $B/${JS#/})\" = 200 ]"
check "css asset 200 ($CSS)" "[ \"\$(curl -s -o /dev/null -w '%{http_code}' $B/${CSS#/})\" = 200 ]"
check "settings button" "grep -q 'juhe-consent-settings-button' <<<\"\$H\""
ok='{"banner":1,"version":1,"id":"0f8e7d6c-5b4a-4321-8abc-0123456789ab","groups":["notwendig","marketing"],"services":[],"url":"/"}'
check "log 204" "[ \"\$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: application/json' --data '$ok' $B/_juhe_consent/log)\" = 204 ]"
bad='{"banner":1,"version":1,"id":"x","groups":["evil"],"services":[],"url":"/"}'
check "log rejects junk 400" "[ \"\$(curl -s -o /dev/null -w '%{http_code}' -X POST --data '$bad' $B/_juhe_consent/log)\" = 400 ]"
check "log GET not allowed" "[ \"\$(curl -s -o /dev/null -w '%{http_code}' $B/_juhe_consent/log)\" = 405 ]"
# The choice stays in localStorage: the page sets no cookie, and a repeat request
# without cookies comes from Contao's HTTP cache. A request cookie Contao does not
# know (the old juhe_consent_<id> cookie) always misses it.
check "page sets no cookie" "! grep -qi '^set-cookie:' <<<\"\$HDR\""
curl -s -o /dev/null $B/ ; curl -s -o /dev/null $B/
check "repeat request from cache" "curl -s -o /dev/null -D - $B/ | grep -qi '^contao-cache:.*fresh'"
check "unknown cookie misses cache" "! curl -s -o /dev/null -D - -H 'Cookie: juhe_consent_1=x' $B/ | grep -qi '^contao-cache:.*fresh'"
# Banner-level options. The anonymous page carries the new config keys
# with their defaults and no logged-in flag; the excluded-page flag is per page.
check "config has lifetime 365" "grep -q '\"lifetime\":365' <<<\"\$H\""
check "config not restricted" "grep -q '\"restricted\":false' <<<\"\$H\""
check "default position attribute" "grep -q 'data-juhe-consent-position=\"bottom-left\"' <<<\"\$H\""
check "kontakt page 200" "[ \"\$(curl -s -o /dev/null -w '%{http_code}' $B/kontakt)\" = 200 ]"
check "kontakt not quiet by default" "curl -s $B/kontakt | grep -q '\"quiet\":false'"
curl -s -o /dev/null $B/kontakt; curl -s -o /dev/null $B/kontakt
check "kontakt from cache" "curl -s -o /dev/null -D - $B/kontakt | grep -qi '^contao-cache:.*fresh'"
# The log address and page setting reach the script (defaults)
check "config has log address" "grep -q '\"logUrl\":\"\\\\/_juhe_consent\\\\/log\"' <<<\"\$H\""
check "config logs the path" "grep -q '\"logPage\":\"path\"' <<<\"\$H\""
check "log strips the query 204" "[ \"\$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: application/json' --data '{\"banner\":1,\"version\":1,\"id\":\"5f8e7d6c-5b4a-4321-8abc-0123456789ab\",\"groups\":[\"notwendig\"],\"services\":[],\"url\":\"/kontakt?x=1\"}' $B/_juhe_consent/log)\" = 204 ]"
check "export without login is not served" "[ \"\$(curl -s -o /dev/null -w '%{http_code}' $B/contao/juhe-consent/log-export)\" != 200 ]"
# The cookie cleanup route expires only configured names the request carries
check "config has cookie address" "grep -q '\"cookieUrl\":\"\\\\/_juhe_consent\\\\/cookies\"' <<<\"\$H\""
CC=$(curl -s -D - -o /dev/null -X POST -H 'Content-Type: application/json' -H 'Cookie: _ga=1; _ga_X=2; juhe_consent_1=3; other=4' --data '{"banner":1,"services":["s3"],"path":"/kontakt"}' $B/_juhe_consent/cookies)
check "cookies 204" "grep -q '^HTTP/1.1 204' <<<\"\$CC\""
check "cookies expires _ga and _ga_X" "grep -qi '^set-cookie: _ga=deleted;' <<<\"\$CC\" && grep -qi '^set-cookie: _ga_X=deleted;' <<<\"\$CC\""
check "cookies keeps other names" "! grep -qiE '^set-cookie: (other|juhe_consent_1)=' <<<\"\$CC\""
check "cookies covers the page path" "grep -qi '^set-cookie: _ga=deleted;.*path=/kontakt;' <<<\"\$CC\""
check "cookies refuses an essential service 400" "[ \"\$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: application/json' --data '{\"banner\":1,\"services\":[\"s1\"]}' $B/_juhe_consent/cookies)\" = 400 ]"
check "cookies refuses text/plain 415" "[ \"\$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: text/plain' --data '{\"banner\":1}' $B/_juhe_consent/cookies)\" = 415 ]"
check "cookies GET not allowed" "[ \"\$(curl -s -o /dev/null -w '%{http_code}' $B/_juhe_consent/cookies)\" = 405 ]"
# The settings opener links to the privacy page (works without JavaScript),
# the opener template is Twig, the preset templates ship, the essential route is POST only
check "opener is a link to the privacy page" "grep -Eq '<a [^>]*juhe-consent-settings-button[^>]*>' <<<\"\$H\" && grep -q 'datenschutz#juhe-consent' <<<\"\$H\""
check "opener opens the dialog" "grep -Eq '<a [^>]*data-juhe-consent-open[^>]*>' <<<\"\$H\""
check "essential route refuses GET" "[ \"\$(curl -s -o /dev/null -w '%{http_code}' $B/contao/juhe-consent/essential/1)\" != 200 ]"
check "essential route refuses an anonymous POST" "! [ \"\$(curl -s -o /dev/null -w '%{http_code}' -X POST --data 'REQUEST_TOKEN=x' $B/contao/juhe-consent/essential/1)\" = 303 ]"
exit $fail
