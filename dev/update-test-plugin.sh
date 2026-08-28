#!/usr/bin/env bash
set -euo pipefail

pluginroot="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
export PLUGIN_ROOT="${PLUGIN_ROOT:-$pluginroot}"

adminroot="admin"
if docker compose -f "$pluginroot/dev/compose.yml" exec -T moodle test -d public/admin; then
    adminroot="public/admin"
fi

docker compose -f "$pluginroot/dev/compose.yml" exec -T moodle \
    php "$adminroot/cli/upgrade.php" --non-interactive
docker compose -f "$pluginroot/dev/compose.yml" exec -T moodle \
    php "$adminroot/cli/purge_caches.php"
