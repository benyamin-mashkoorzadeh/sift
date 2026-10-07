#!/bin/bash
set -e

if [ -z "${POSTGRES_TEST_DB:-}" ]; then
    exit 0
fi

psql \
    --username "$POSTGRES_USER" \
    --dbname postgres \
    --set=test_db="$POSTGRES_TEST_DB" \
    --set=db_owner="$POSTGRES_USER" \
    --set=ON_ERROR_STOP=1 <<'SQL'
SELECT format('CREATE DATABASE %I OWNER %I', :'test_db', :'db_owner')
WHERE NOT EXISTS (
    SELECT 1 FROM pg_database WHERE datname = :'test_db'
)\gexec
SQL
