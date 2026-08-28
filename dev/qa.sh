#!/usr/bin/env bash
set -euo pipefail

plugin_root="$(cd "$(dirname "$0")/.." && pwd)"
docker run --rm -v "$plugin_root:/plugin:ro" php:8.3-cli \
    sh -lc 'find /plugin -name "*.php" -print0 | xargs -0 -n1 php -l'
docker run --rm -v "$plugin_root:/plugin:ro" composer:2 sh -lc \
    'composer global config --no-plugins allow-plugins.dealerdirect/phpcodesniffer-composer-installer true >/dev/null &&
     composer global require --no-interaction moodlehq/moodle-cs:^3 >/dev/null 2>&1 &&
     /tmp/vendor/bin/phpcs --standard=moodle /plugin --extensions=php'

