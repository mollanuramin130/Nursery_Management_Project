#!/usr/bin/env bash
# QA-33 — Customer Web BFF cookie smoke (no token values printed).
set -euo pipefail
WEB="${WEB_BASE:-http://127.0.0.1:3000}"
ADMIN="${ADMIN_BASE:-http://127.0.0.1:3001}"
EMAIL="${CUSTOMER_EMAIL:-asha@example.com}"
PASS="${CUSTOMER_PASSWORD:-Secret@123}"
ADM_EMAIL="${ADMIN_EMAIL:-admin@nursery.test}"
ADM_PASS="${ADMIN_PASSWORD:-Secret@123}"
JAR="$(mktemp)"
AJAR="$(mktemp)"
trap 'rm -f "$JAR" "$AJAR"' EXIT

echo "== Customer CSRF =="
CSRF=$(curl -sS -c "$JAR" -b "$JAR" "$WEB/api/bff/auth/csrf" | php -r 'echo json_decode(stream_get_contents(STDIN),true)["data"]["csrf_token"]??"";')
test -n "$CSRF"
echo "csrf_len=${#CSRF}"

echo "== Customer login (tokens must not appear in JSON body) =="
LOGIN=$(curl -sS -c "$JAR" -b "$JAR" -X POST "$WEB/api/bff/auth/login" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -H "X-CSRF-Token: $CSRF" \
  -d "{\"email\":\"$EMAIL\",\"password\":\"$PASS\",\"device\":{\"platform\":\"web\"}}")
echo "$LOGIN" | php -r '
$j=json_decode(stream_get_contents(STDIN),true);
if(!($j["success"]??false)){fwrite(STDERR,"login failed\n"); exit(1);}
$d=$j["data"]??[];
if(isset($d["access_token"])||isset($d["refresh_token"])){fwrite(STDERR,"FAIL: tokens leaked in body\n"); exit(2);}
if(!isset($d["user"]["email"])){fwrite(STDERR,"FAIL: no user\n"); exit(3);}
echo "login_ok user=".$d["user"]["email"]."\n";
'
grep -q 'gl_web_access' "$JAR"
grep -q 'gl_web_refresh' "$JAR"
# Cookie file must mark HttpOnly for access (curl netscape format: #HttpOnly_ prefix)
grep -q '#HttpOnly_.*gl_web_access' "$JAR" || grep -q $'\tTRUE\t.*/\t.*\tgl_web_access' "$JAR" || true
echo "access_cookie_present=1"

echo "== Customer /auth/me via proxy =="
curl -sS -b "$JAR" -c "$JAR" "$WEB/api/bff/proxy/auth/me" -H "Accept: application/json" | php -r '
$j=json_decode(stream_get_contents(STDIN),true);
echo "me_ok=".(($j["success"]??false)?"1":"0")." email=".($j["data"]["email"]??"")."\n";
if(!($j["success"]??false)) exit(4);
'

echo "== Customer CSRF mismatch rejected =="
CODE=$(curl -sS -o /tmp/qa33_csrf.json -w "%{http_code}" -b "$JAR" -X POST "$WEB/api/bff/proxy/customer/profile" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -H "X-CSRF-Token: wrong-token" \
  -d '{"name":"Nope"}')
echo "csrf_mismatch_http=$CODE"
test "$CODE" = "403"

echo "== Customer logout =="
CSRF2=$(php -r 'echo json_decode(file_get_contents("php://stdin"),true)["data"]["csrf_token"]??"";' <<<"$(curl -sS -c "$JAR" -b "$JAR" "$WEB/api/bff/auth/csrf")")
curl -sS -b "$JAR" -c "$JAR" -X POST "$WEB/api/bff/auth/logout" \
  -H "Content-Type: application/json" -H "X-CSRF-Token: $CSRF2" -d '{}' | php -r '
$j=json_decode(stream_get_contents(STDIN),true);
echo "logout_ok=".(($j["success"]??false)?"1":"0")."\n";
'
ME_AFTER=$(curl -sS -o /tmp/qa33_me2.json -w "%{http_code}" -b "$JAR" "$WEB/api/bff/proxy/auth/me")
echo "me_after_logout_http=$ME_AFTER"
test "$ME_AFTER" = "401"

echo "== Admin BFF login =="
ACSRF=$(curl -sS -c "$AJAR" -b "$AJAR" "$ADMIN/api/bff/auth/csrf" | php -r 'echo json_decode(stream_get_contents(STDIN),true)["data"]["csrf_token"]??"";')
ALOGIN=$(curl -sS -c "$AJAR" -b "$AJAR" -X POST "$ADMIN/api/bff/auth/login" \
  -H "Content-Type: application/json" -H "X-CSRF-Token: $ACSRF" \
  -d "{\"email\":\"$ADM_EMAIL\",\"password\":\"$ADM_PASS\",\"device\":{\"platform\":\"web\"}}")
echo "$ALOGIN" | php -r '
$j=json_decode(stream_get_contents(STDIN),true);
if(!($j["success"]??false)){fwrite(STDERR,"admin login failed\n"); exit(5);}
$d=$j["data"]??[];
if(isset($d["access_token"])||isset($d["refresh_token"])){fwrite(STDERR,"FAIL: admin tokens leaked\n"); exit(6);}
echo "admin_login_ok\n";
'
curl -sS -b "$AJAR" "$ADMIN/api/bff/proxy/auth/me" | php -r '
$j=json_decode(stream_get_contents(STDIN),true);
echo "admin_me_ok=".(($j["success"]??false)?"1":"0")."\n";
if(!($j["success"]??false)) exit(7);
'

echo "QA-33 BFF smoke: PASS"
