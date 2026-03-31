#!/bin/sh

if docker info >/dev/null 2>&1; then
    exec docker "$@"
fi

if [ "$(id -u)" -eq 0 ]; then
    exec docker "$@"
fi

if ! command -v sudo >/dev/null 2>&1; then
    echo "docker is not available for the current user and sudo is not installed." >&2
    exit 1
fi

exec sudo docker "$@"
