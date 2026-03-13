#!/usr/bin/env bash
set -euo pipefail

API_BASE="${API_BASE:-http://localhost:8000/api}"
PYTHON_BIN="${PYTHON_BIN:-python3}"
RAND_SUFFIX="$(${PYTHON_BIN} - <<'PY'
import uuid
print(uuid.uuid4().hex[:8])
PY
)"
USER1_EMAIL="qa_${RAND_SUFFIX}_1@whisp.local"
USER2_EMAIL="qa_${RAND_SUFFIX}_2@whisp.local"
USER1_NAME="qa_${RAND_SUFFIX}_1"
USER2_NAME="qa_${RAND_SUFFIX}_2"
PASSWORD="Test123!"
TMPDIR_PATH="$(mktemp -d)"
trap 'rm -rf "$TMPDIR_PATH"' EXIT

request() {
  local method="$1"
  local url="$2"
  local token="${3:-}"
  local body="${4:-}"
  local outfile="$5"
  local curl_args=(-sS -X "$method" "$API_BASE$url" -H 'Content-Type: application/json' -o "$outfile" -w '%{http_code}')
  if [[ -n "$token" ]]; then
    curl_args+=(-H "Authorization: Bearer $token")
  fi
  if [[ -n "$body" ]]; then
    curl_args+=(-d "$body")
  fi
  curl "${curl_args[@]}"
}

json_field() {
  local file="$1"
  local expr="$2"
  "$PYTHON_BIN" - <<PY
import json
from pathlib import Path
obj = json.loads(Path('$file').read_text())
value = obj$expr
print(value)
PY
}

assert_status() {
  local expected="$1"
  local actual="$2"
  local file="$3"
  if [[ "$expected" != "$actual" ]]; then
    echo "Expected HTTP $expected, got $actual"
    cat "$file"
    exit 1
  fi
}

file1="$TMPDIR_PATH/register1.json"
status=$(request POST '/register' '' "{\"username\":\"$USER1_NAME\",\"email\":\"$USER1_EMAIL\",\"password\":\"$PASSWORD\"}" "$file1")
assert_status 201 "$status" "$file1"
TOKEN1=$(json_field "$file1" "['token']")
USER1_ID=$(json_field "$file1" "['user']['id']")

file2="$TMPDIR_PATH/register2.json"
status=$(request POST '/register' '' "{\"username\":\"$USER2_NAME\",\"email\":\"$USER2_EMAIL\",\"password\":\"$PASSWORD\"}" "$file2")
assert_status 201 "$status" "$file2"
TOKEN2=$(json_field "$file2" "['token']")
USER2_ID=$(json_field "$file2" "['user']['id']")

file3="$TMPDIR_PATH/friend_add.json"
status=$(request POST '/friends/add' "$TOKEN1" "{\"target_id\":\"$USER2_ID\"}" "$file3")
assert_status 200 "$status" "$file3"

file4="$TMPDIR_PATH/requests.json"
status=$(request GET '/friends/requests' "$TOKEN2" '' "$file4")
assert_status 200 "$status" "$file4"
REQUEST_ID=$(json_field "$file4" "[0]['request_id']")

file5="$TMPDIR_PATH/accept.json"
status=$(request POST '/friends/accept' "$TOKEN2" "{\"request_id\":$REQUEST_ID}" "$file5")
assert_status 200 "$status" "$file5"

file6="$TMPDIR_PATH/open_dm.json"
status=$(request POST '/chat/open' "$TOKEN1" "{\"target_id\":\"$USER2_ID\"}" "$file6")
assert_status 200 "$status" "$file6"
ROOM_ID=$(json_field "$file6" "['room_id']")

file7="$TMPDIR_PATH/send_message.json"
status=$(request POST '/messages/send' "$TOKEN1" "{\"room_id\":$ROOM_ID,\"content\":\"Ahoj z testu\"}" "$file7")
assert_status 200 "$status" "$file7"

file8="$TMPDIR_PATH/remove_friend.json"
status=$(request POST '/friends/remove' "$TOKEN1" "{\"friend_id\":\"$USER2_ID\"}" "$file8")
assert_status 200 "$status" "$file8"

file9="$TMPDIR_PATH/open_dm_after_remove.json"
status=$(request POST '/chat/open' "$TOKEN1" "{\"target_id\":\"$USER2_ID\"}" "$file9")
assert_status 403 "$status" "$file9"

file10="$TMPDIR_PATH/history_after_remove.json"
status=$(request GET "/messages/history?room_id=$ROOM_ID" "$TOKEN1" '' "$file10")
assert_status 403 "$status" "$file10"

echo 'Smoke test passed.'
