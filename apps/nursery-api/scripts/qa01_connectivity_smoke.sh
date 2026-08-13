#!/usr/bin/env bash
# QA-01 extended connectivity smoke (API only; never prints tokens).
set -uo pipefail
BASE="${API_BASE_URL:-http://127.0.0.1:8000/api/v1}"
PASS=0
FAIL=0

check() {
  local name="$1"
  local ok="$2"
  local detail="${3:-}"
  if [[ "$ok" == "1" ]]; then
    echo "PASS $name ${detail}"
    PASS=$((PASS + 1))
  else
    echo "FAIL $name ${detail}"
    FAIL=$((FAIL + 1))
  fi
}

login_with_retry() {
  local email="$1"
  local out="$2"
  local attempt=1
  local code=000
  while [[ $attempt -le 2 ]]; do
    code=$(curl -s -o "$out" -w "%{http_code}" -X POST "$BASE/auth/login" \
      -H 'Content-Type: application/json' -H 'Accept: application/json' \
      -d "{\"email\":\"$email\",\"password\":\"Secret@123\",\"device\":{\"platform\":\"web\"}}")
    if [[ "$code" == "429" && $attempt -eq 1 ]]; then
      echo "THROTTLED $email — wait 65s..."
      sleep 65
      attempt=$((attempt + 1))
      continue
    fi
    break
  done
  echo "$code"
}

token_from() {
  python3 - "$1" <<'PY'
import json,sys
d=json.load(open(sys.argv[1]))
print((d.get("data") or {}).get("access_token") or "")
PY
}

echo "== Health =="
curl -s -o /tmp/qa01x_ready.json -w "%{http_code}" "$BASE/health/ready" >/tmp/qa01x_ready.code
READY_CODE=$(cat /tmp/qa01x_ready.code)
head -c 200 /tmp/qa01x_ready.json; echo
python3 - <<'PY'
import json
j=json.load(open("/tmp/qa01x_ready.json"))
raise SystemExit(0 if j.get("status")=="ok" and j.get("database")=="healthy" else 1)
PY
check "health/ready" "$([[ $? -eq 0 && "$READY_CODE" == "200" ]] && echo 1 || echo 0)" "http=$READY_CODE"

echo "== Customer path =="
CODE=$(login_with_retry "asha@example.com" /tmp/qa01x_login.json)
TOK=$(token_from /tmp/qa01x_login.json)
check "customer login" "$([[ "$CODE" == "200" && -n "$TOK" ]] && echo 1 || echo 0)" "http=$CODE"
AUTH="Authorization: Bearer $TOK"

CODE=$(curl -s -o /tmp/qa01x.json -w "%{http_code}" -H "$AUTH" -H 'Accept: application/json' "$BASE/auth/me")
check "customer /auth/me" "$([[ "$CODE" == "200" ]] && echo 1 || echo 0)" "http=$CODE"

CODE=$(curl -s -o /tmp/qa01x.json -w "%{http_code}" -H 'Accept: application/json' "$BASE/products?per_page=1")
PID=$(python3 - <<'PY'
import json
d=json.load(open("/tmp/qa01x.json"))
data=d.get("data")
if isinstance(data, list) and data:
    print(data[0].get("id") or "")
elif isinstance(data, dict):
    arr = data.get("data") or data.get("items") or []
    print((arr[0].get("id") if arr else "") or "")
else:
    print("")
PY
)
check "GET /products" "$([[ "$CODE" == "200" && -n "$PID" ]] && echo 1 || echo 0)" "http=$CODE pid=$PID"

CODE=$(curl -s -o /tmp/qa01x.json -w "%{http_code}" -H "$AUTH" -H 'Accept: application/json' "$BASE/cart")
check "GET /cart" "$([[ "$CODE" == "200" ]] && echo 1 || echo 0)" "http=$CODE"

if [[ -n "$PID" ]]; then
  CODE=$(curl -s -o /tmp/qa01x.json -w "%{http_code}" -X POST -H "$AUTH" \
    -H 'Content-Type: application/json' -H 'Accept: application/json' \
    -d "{\"product_id\":$PID,\"quantity\":1}" "$BASE/cart/items")
  check "POST /cart/items" "$([[ "$CODE" == "200" || "$CODE" == "201" ]] && echo 1 || echo 0)" "http=$CODE"
fi

AID=$(curl -s -H "$AUTH" -H 'Accept: application/json' "$BASE/customer/addresses" \
  | python3 -c "import sys,json; d=json.load(sys.stdin); a=d.get('data') or []; print(a[0]['id'] if a else '')")
SID=$(curl -s -H 'Accept: application/json' "$BASE/shipping/methods" \
  | python3 -c "import sys,json; d=json.load(sys.stdin); a=d.get('data') or []; print(a[0]['id'] if a else '')")
if [[ -n "$AID" && -n "$SID" ]]; then
  CODE=$(curl -s -o /tmp/qa01x.json -w "%{http_code}" -X POST -H "$AUTH" \
    -H 'Content-Type: application/json' -H 'Accept: application/json' \
    -d "{\"address_id\":$AID,\"shipping_method_id\":$SID}" "$BASE/checkout/preview")
  check "POST /checkout/preview" "$([[ "$CODE" == "200" ]] && echo 1 || echo 0)" "http=$CODE"
else
  check "POST /checkout/preview" 0 "missing address/shipping"
fi

echo "== Admin path =="
sleep 3
CODE=$(login_with_retry "admin@nursery.test" /tmp/qa01x_login.json)
ATOK=$(token_from /tmp/qa01x_login.json)
check "admin login" "$([[ "$CODE" == "200" && -n "$ATOK" ]] && echo 1 || echo 0)" "http=$CODE"
AAUTH="Authorization: Bearer $ATOK"
for path in "/auth/me" "/admin/dashboard" "/admin/orders?per_page=1" "/admin/products?per_page=1"; do
  CODE=$(curl -s -o /tmp/qa01x.json -w "%{http_code}" -H "$AAUTH" -H 'Accept: application/json' "$BASE$path")
  check "GET $path" "$([[ "$CODE" == "200" ]] && echo 1 || echo 0)" "http=$CODE"
done

echo "== Result: PASS=$PASS FAIL=$FAIL =="
[[ "$FAIL" -eq 0 ]]
