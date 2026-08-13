#!/usr/bin/env bash
# QA-29 lightweight UIAutomator helpers for Vivo (2d3714f).
set -euo pipefail
DEV="${DEV:-2d3714f}"
ADB=(adb -s "$DEV")

dump() {
  "${ADB[@]}" shell uiautomator dump /sdcard/uidump.xml >/dev/null
  "${ADB[@]}" shell cat /sdcard/uidump.xml
}

desc_bounds() {
  local desc="$1"
  dump | tr '>' '>\n' | grep -F "content-desc=\"$desc\"" | head -1 \
    | sed -n 's/.*bounds="\[\([0-9]*\),\([0-9]*\)\]\[\([0-9]*\),\([0-9]*\)\]".*/\1 \2 \3 \4/p'
}

tap_desc() {
  local b
  b=$(desc_bounds "$1")
  if [[ -z "$b" ]]; then
    echo "NOT_FOUND: $1" >&2
    return 1
  fi
  local x1 y1 x2 y2
  read -r x1 y1 x2 y2 <<<"$b"
  local x=$(( (x1 + x2) / 2 ))
  local y=$(( (y1 + y2) / 2 ))
  echo "TAP $1 -> $x,$y"
  "${ADB[@]}" shell input tap "$x" "$y"
}

tap_desc_contains() {
  local needle="$1"
  local line
  line=$(dump | tr '>' '>\n' | grep -F "content-desc=" | grep -F "$needle" | head -1 || true)
  if [[ -z "$line" ]]; then
    echo "NOT_FOUND_CONTAINS: $needle" >&2
    return 1
  fi
  local x1 y1 x2 y2
  read -r x1 y1 x2 y2 <<<"$(echo "$line" | sed -n 's/.*bounds="\[\([0-9]*\),\([0-9]*\)\]\[\([0-9]*\),\([0-9]*\)\]".*/\1 \2 \3 \4/p')"
  local x=$(( (x1 + x2) / 2 ))
  local y=$(( (y1 + y2) / 2 ))
  echo "TAP_CONTAINS $needle -> $x,$y"
  "${ADB[@]}" shell input tap "$x" "$y"
}

shot() {
  local name="$1"
  "${ADB[@]}" exec-out screencap -p > "/tmp/qa29_${name}.png"
  echo "SHOT /tmp/qa29_${name}.png"
}

list_descs() {
  dump | tr '>' '>\n' | grep -oE 'content-desc="[^"]+"' | sed 's/content-desc="//;s/"$//' | head -80
}

case "${1:-}" in
  dump) dump ;;
  list) list_descs ;;
  tap) tap_desc "$2" ;;
  tapc) tap_desc_contains "$2" ;;
  shot) shot "$2" ;;
  *) echo "usage: $0 dump|list|tap <desc>|tapc <needle>|shot <name>" ;;
esac
