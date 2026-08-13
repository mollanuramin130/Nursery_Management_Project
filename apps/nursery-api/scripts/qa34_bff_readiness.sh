#!/usr/bin/env bash
# QA-34 — BFF deployment readiness (local TEST + config contract). No secrets printed.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
WEB_ENV="$ROOT/apps/nursery-web"
ADM_ENV="$ROOT/apps/nursery-admin"

echo "== Env example contract =="
for f in \
  "$WEB_ENV/.env.example" \
  "$WEB_ENV/.env.staging.example" \
  "$WEB_ENV/.env.production.example" \
  "$ADM_ENV/.env.example" \
  "$ADM_ENV/.env.staging.example" \
  "$ADM_ENV/.env.production.example"
do
  grep -q 'API_PROXY_TARGET' "$f" || { echo "MISSING API_PROXY_TARGET in $f"; exit 1; }
done
grep -q 'COOKIE_SECURE=true' "$WEB_ENV/.env.production.example"
grep -q 'COOKIE_SECURE=true' "$ADM_ENV/.env.production.example"
grep -q 'COOKIE_SECURE=true' "$WEB_ENV/.env.staging.example"
grep -q 'COOKIE_SECURE=true' "$ADM_ENV/.env.staging.example"
echo "env_examples_ok=1"

echo "== Unit cookie/BFF policy =="
(cd "$WEB_ENV" && npm run test:unit >/tmp/qa34_web_unit.txt)
(cd "$ADM_ENV" && npm run test:unit >/tmp/qa34_admin_unit.txt)
echo "unit_ok=1"

echo "== QA-33 BFF smoke =="
bash "$ROOT/apps/nursery-api/scripts/qa33_bff_smoke.sh"

echo "== Local HTTP cookie attrs (Secure must be 0) =="
JAR="$(mktemp)"
trap 'rm -f "$JAR"' EXIT
CSRF=$(curl -sS -c "$JAR" -b "$JAR" "http://127.0.0.1:3000/api/bff/auth/csrf" | php -r 'echo json_decode(stream_get_contents(STDIN),true)["data"]["csrf_token"]??"";')
curl -sS -D /tmp/qa34_hdrs.txt -o /tmp/qa34_body.json -c "$JAR" -b "$JAR" \
  -X POST "http://127.0.0.1:3000/api/bff/auth/login" \
  -H "Content-Type: application/json" -H "X-CSRF-Token: $CSRF" \
  -d '{"email":"asha@example.com","password":"Secret@123","device":{"platform":"web"}}' >/dev/null
php -r '
$h=file_get_contents("/tmp/qa34_hdrs.txt");
preg_match_all("/^set-cookie:\s*([^=]+)=/mi",$h,$names);
$names=$names[1]??[];
foreach(["gl_web_access","gl_web_refresh"] as $need){
  if(!in_array($need,$names,true)){fwrite(STDERR,"missing $need\n"); exit(2);}
}
preg_match("/^set-cookie:\s*gl_web_access=([^;]*);(.*)$/mi",$h,$m);
$attrs=strtolower($m[2]??"");
if(!str_contains($attrs,"httponly")){fwrite(STDERR,"access not HttpOnly\n"); exit(3);}
if(str_contains($attrs,"secure")){fwrite(STDERR,"local HTTP unexpectedly Secure\n"); exit(4);}
if(!str_contains($attrs,"samesite=lax")){fwrite(STDERR,"SameSite not Lax\n"); exit(5);}
$j=json_decode(file_get_contents("/tmp/qa34_body.json"),true);
if(isset($j["data"]["access_token"])||isset($j["data"]["refresh_token"])){fwrite(STDERR,"token leak\n"); exit(6);}
echo "local_http_cookie_attrs_ok=1\n";
'

echo "== Missing CSRF rejected =="
CODE=$(curl -sS -o /dev/null -w "%{http_code}" -b "$JAR" -X POST \
  "http://127.0.0.1:3000/api/bff/proxy/customer/profile" \
  -H "Content-Type: application/json" -d '{"name":"X"}')
echo "missing_csrf_http=$CODE"
test "$CODE" = "403"

echo "== Browser must not need direct API CORS credentials =="
grep -q "supports_credentials' => false" "$ROOT/apps/nursery-api/config/cors.php"
echo "cors_credentials_false=1"

echo "QA-34 BFF readiness: PASS"
echo "NOTE: Live HTTPS Secure cookie on a staging host remains UNVERIFIED in this script;"
echo "      production policy is covered by unit tests (NODE_ENV=production / COOKIE_SECURE=true)."
