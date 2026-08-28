#!/usr/bin/env bash
set -euo pipefail

pluginroot="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
release="${1:-0.3.0-alpha}"
package="local_criteriaoutcomes-${release}"
staging="$(mktemp -d)"
trap 'rm -rf "$staging"' EXIT

grep -Fq "\$plugin->component = 'local_criteriaoutcomes';" "$pluginroot/version.php"
grep -Fq "\$plugin->release = '${release}';" "$pluginroot/version.php"

mkdir -p "$staging/criteriaoutcomes" "$pluginroot/dist"
for path in backup restore classes cli db docs examples lang tests CHANGES.md LICENSE README.md index.php lib.php quiz.php \
        quiz_mapping.php quiz_evidence.php assessment.php student_progress.php criterion_progress.php boe.php \
        curriculum_manage.php import_history.php json.php version.php; do
    cp -a "$pluginroot/$path" "$staging/criteriaoutcomes/"
done

find "$staging" -type f \( -name '*.log' -o -name '.phpunit.result.cache' -o -name '*~' \) -delete
(cd "$staging" && zip -q -r "$pluginroot/dist/$package.zip" criteriaoutcomes)
(cd "$pluginroot/dist" && sha256sum "$package.zip" > "$package.zip.sha256")
unzip -t "$pluginroot/dist/$package.zip"
printf 'Built %s\n' "$pluginroot/dist/$package.zip"
printf 'SHA-256: '
cut -d' ' -f1 "$pluginroot/dist/$package.zip.sha256"
