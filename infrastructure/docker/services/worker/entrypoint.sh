#!/bin/sh
set -e

if [ "$PROJECT_START_WORKERS" = "False" ]; then
    echo "Worker not started"
    exit 0
fi

exec "$@"
