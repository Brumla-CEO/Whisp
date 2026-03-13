#!/usr/bin/env bash
set -euo pipefail

php backend/tests/validator_smoke_test.php
(
  cd frontend
  npm test
)
./tests/api_smoke_test.sh
