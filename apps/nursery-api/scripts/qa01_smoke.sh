#!/usr/bin/env bash
# QA-01 smoke: health + seeded logins (does not print tokens).
# Retries once on HTTP 429 (auth throttle) — not a connectivity failure.
set -euo pipefail
BASE="${API_BASE_URL:-http://127.0.0.1:8000/api/v1}"

echo "== Health ready =="
curl -sf "$BASE/health/ready" | head -c 200
echo

login_ok() {
  local email="$1"
  local attempt=1
  local code
  while [[ $attempt -le 2 ]]; do
    code=$(curl -s -o /tmp/qa01_login.json -w "%{http_code}" \
      -X POST "$BASE/auth/login" \
      -H 'Content-Type: application/json' \
      -H 'Accept: application/json' \
      -d "{\"email\":\"$email\",\"password\":\"Secret@123\",\"device\":{\"platform\":\"web\"}}")
    if [[ "$code" == "429" && $attempt -eq 1 ]]; then
      echo "THROTTLED login $email — waiting 65s then retry..."
      sleep 65
      attempt=$((attempt + 1))
      continue
    fi
    break
  done
  if [[ "$code" != "200" ]]; then
    echo "FAIL login $email HTTP $code"
    head -c 200 /tmp/qa01_login.json; echo
    if [[ "$code" == "429" ]]; then
      echo "HINT: auth throttle — wait ~60s and retry (not a connectivity failure)."
    fi
    return 1
  fi
  if ! grep -q '"success":true' /tmp/qa01_login.json; then
    echo "FAIL login $email envelope"
    return 1
  fi
  if ! grep -q 'access_token' /tmp/qa01_login.json; then
    echo "FAIL login $email missing access_token"
    return 1
  fi
  echo "PASS login $email"
  # Brief pause between accounts to reduce throttle collisions.
  sleep 2
}

echo "== Seeded logins =="
login_ok "asha@example.com"
login_ok "admin@nursery.test"
echo "QA-01 smoke PASS"
