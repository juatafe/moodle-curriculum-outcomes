# Reproducible testing

The plugin repository does not vendor Moodle or database data. The development compose file mounts the plugin read-only, so development infrastructure is not part of a distributable plugin ZIP.

## Clean Moodle matrix

Requirements: Git, Docker and Docker Compose.

```bash
runtime="$(mktemp -d)"
# Use MOODLE_405_STABLE, MOODLE_500_STABLE, then MOODLE_501_STABLE.
git clone --depth 1 --branch MOODLE_501_STABLE https://github.com/moodle/moodle.git "$runtime/moodle"
docker run --rm -e COMPOSER_ROOT_VERSION=5.1.6 \
  -v "$runtime/moodle:/app" -w /app composer:2 \
  composer install --no-interaction --prefer-dist \
  --ignore-platform-req=ext-gd --ignore-platform-req=ext-intl
mkdir -p "$runtime/moodledata"
chmod 0777 "$runtime/moodledata"
export MOODLE_ROOT="$runtime/moodle"
export MOODLE_DATA="$runtime/moodledata"
export PLUGIN_ROOT="$(pwd)"
docker compose -f dev/compose.yml up -d db
docker compose -f dev/compose.yml run --rm moodle php admin/cli/install.php \
  --non-interactive --agree-license --wwwroot=http://localhost:8080 \
  --dataroot=/var/www/moodledata --dbtype=pgsql --dbhost=db --dbname=moodle \
  --dbuser=moodle --dbpass=moodle --fullname='Criteria Outcomes Test' \
  --shortname=criteria --adminuser=admin --adminpass='Admin_test1!' \
  --adminemail=admin@example.invalid
docker compose -f dev/compose.yml up -d moodle
```

Repeat with a valid non-default `--prefix=tst_`. Moodle 5.1 uses `public/local`; 4.5/5.0 use `local`. The entrypoints detect topology rather than a version number. The compose file supplies PostgreSQL; replace its database service and installer arguments with a Moodle-supported MariaDB/MySQL image to exercise the second engine.

Enable Outcomes and verify the plugin installation:

```bash
docker compose -f dev/compose.yml exec moodle php admin/cli/cfg.php --name=enableoutcomes --set=1
docker compose -f dev/compose.yml exec moodle php admin/cli/upgrade.php --non-interactive
docker compose -f dev/compose.yml exec moodle php admin/cli/purge_caches.php
```

For the repository compose environment, `dev/update-test-plugin.sh` performs those two official CLI operations in that order and detects Moodle's `admin`/`public/admin` topology. Purging caches after new language keys avoids transient `Invalid get_string()` false failures.

## Manual language-pack setup

Moodle 4.5–5.1 does not ship a supported general-purpose language-pack installation CLI. Prepare a browser QA site through **Site administration → Language → Language packs**. Install `es`, then `ca`, `eu`, `gl`, and finally `ca_valencia`. Install Catalan before Valencian because `ca_valencia` is a child locale and Moodle falls back to `ca`; the plugin intentionally does not duplicate a full Valencian catalog.

After installation, select English, Español, Català, Valencià, Euskara and Galego as the preferred language and open the plugin home, BOE import, JSON import, curriculum management, import history and student progress. No page may show `[[missingstring]]`. Language packs belong to the test Moodle, never to the production plugin, and must not be manually downloaded into `moodledata/lang`.

## PHPUnit

Add these values before the final `require_once` in the temporary Moodle `config.php`:

```php
$CFG->phpunit_prefix = 'phpu_';
$CFG->phpunit_dataroot = '/var/www/phpunitdata';
```

Mount a writable `/var/www/phpunitdata`, then run:

```bash
docker compose -f dev/compose.yml exec moodle php public/admin/tool/phpunit/cli/init.php
docker compose -f dev/compose.yml exec moodle vendor/bin/phpunit \
  --testsuite local_criteriaoutcomes_testsuite
```

The integration suite creates course-local scales, imports native Outcomes, exercises ownership conflicts and safe scale changes, creates assignments through Moodle's module generator, verifies multiple evidence grade items, and performs a full course backup/restore with Outcome ID remapping.

## Static QA

```bash
bash dev/qa.sh
```

Expected result: every PHP file passes `php -l`; Moodle PHPCS reports zero errors and zero warnings.

## Behat

Add portable Behat settings to the temporary Moodle `config.php` (never to the plugin):

```php
$CFG->behat_wwwroot = 'http://moodle';
$CFG->behat_prefix = 'bht_';
$CFG->behat_dataroot = '/var/www/behatdata';
$CFG->behat_config = ['default' => ['extensions' => [
    'Behat\\MinkExtension' => ['webdriver' => [
        'wd_host' => 'http://selenium:4444/wd/hub', 'browser' => 'chrome',
    ]],
]]];
```

Start Moodle and `selenium/standalone-chrome` on the same Docker network, then run:

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --config /var/www/behatdata/behatrun/behat/behat.yml \
  --tags=@local_criteriaoutcomes
```

## Release ZIP

```bash
tools/build-release.sh 0.4.0-dev
unzip -l dist/local_criteriaoutcomes-0.4.0-dev.zip
unzip -t dist/local_criteriaoutcomes-0.4.0-dev.zip
sha256sum -c dist/local_criteriaoutcomes-0.4.0-dev.zip.sha256
```

The sole top-level ZIP directory must be `criteriaoutcomes/`. Install it through **Site administration → Plugins → Install plugins** in a clean site, complete Notifications, import the example, and uninstall it through the plugin overview. Verify with Moodle DML or the database console that `local_crout_*` tables are gone while the relevant `grade_outcomes`, `grade_items`, and `grade_grades` rows remain.

## Manual web checks

Test as admin, editing teacher, non-editing teacher and student. With Outcomes disabled, the page must explain the prerequisite without failing. With Outcomes enabled, import `examples/curriculum.json`, verify the preview statuses, and create two assignments:

- Activity A: `RA1.a`.
- Activity B: `RA1.a` and `RA1.c`.

Grade the normal activity item and Outcome items separately. Confirm that the plugin report shows two pieces of evidence for `RA1.a`, one for `RA1.c`, and never copies the activity grade. Attempt a scale change afterwards; it must be blocked while text and metadata changes remain independent.

## Results recorded for 0.1.0-alpha

- Moodle 4.5.13+ build 20260818, PHP 8.3.33, PostgreSQL 16.15, prefix `tstf_`: clean install; PHPUnit 9/32 passed.
- Moodle 5.0.9 build 20260810, PHP 8.3.33, PostgreSQL 16.15, prefix `tst_`: clean install; PHPUnit 9/32 passed; focused Behat passed (2 scenarios, 27 steps).
- Moodle 5.1.6+ build 20260818, PHP 8.3.33, PostgreSQL 16.15: PHPUnit 9/32 passed after the final changes.
- Moodle 5.1.6+ build 20260818, PHP 8.3.33, MariaDB 11.4.13, prefix `qa_`: clean install; PHPUnit 9/32 passed.
- The integration suite covers native assignment evidence, independent activity/Outcome grades, and restore as a new course. Restore merge remains experimental.
- Browser role checks confirmed administrator and editing-teacher access. Direct URLs as non-editing teacher and student raised Moodle's `nopermissions` exception at `require_capability()`.
- Assignment is the tested reference module. Forum and Quiz were not promoted to tested modules in this release because a complete native web grading flow was not executed.
- The generated ZIP was extracted as the sole plugin source into a second clean Moodle 5.0 site with prefix `zip_`; installation registered version `2026082502` and created all three plugin tables.
- CLI uninstall removed all three plugin tables. A controlled core Outcome, its grade item, and its grade each remained present (counts `1/1/1`) after uninstall.

## Results recorded for 0.2.0-dev

- Moodle 4.5.13+ (Build: 20260818), PHP 8.3.33, PostgreSQL 16.15: 18 PHPUnit tests and 70 assertions passed.
- Moodle 5.0.9 (Build: 20260810), PHP 8.3.33, PostgreSQL 16.15: 18 PHPUnit tests and 70 assertions passed.
- Moodle 5.1.6+ (Build: 20260818), PHP 8.3.33, PostgreSQL 16.15: 18 PHPUnit tests and 70 assertions passed.
- Moodle 5.1.6+ (Build: 20260818), PHP 8.3.33, MariaDB 11.4.13: 18 PHPUnit tests and 70 assertions passed.
- Real Moodle 5.0 browser workflow: 1 Quiz mapping scenario and 26 steps passed.
- Real upgrade from the immutable 0.1 source created both Quiz tables, preserved a pre-upgrade framework, and registered version `2026082600`.
- Real Question Engine coverage includes true/false questions, a random slot with forced selection, always-latest version replacement, two separate attempts, and an essay before/after manual grading. Other question types are not claimed as tested.
- Backup/restore creates a different destination `quiz_slots.id` and verifies that the restored mapping uses that new ID.
- `bash dev/qa.sh`: PHP lint and Moodle PHPCS passed with zero errors and zero warnings.
- Full matrix re-verified 2026-08-26: all four Moodle+DB combinations pass consistently.

## Results recorded for 0.3.0-alpha

- Moodle 4.5.13+, 5.0.9 and 5.1.6+ with PHP 8.3/PostgreSQL 16: 55 PHPUnit tests and 191 assertions passed on each target.
- Moodle 5.1.6+ with PHP 8.3/MariaDB 11.4: the same 55 tests and 191 assertions passed.
- Moodle 5.0.9 browser suite: 4 scenarios and 88 steps passed, including import, Quiz mapping, teacher draft/release, student-only released visibility and unread-to-read feedback transition.
- Backup coverage runs both `users = false` and `users = true`: definitions always restore, all four user-data families are absent without user information, and assessment/checklist/judgement/read records restore through Moodle mappings with user information.
- A real `mod_assign` native rubric verifies one-to-one, many-to-one and one-to-many dimension mappings; selected levels, scores and remarks remain separate and no rubric total is propagated.
- Privacy tests cover metadata, subject/grader discovery, export, context deletion and isolated user deletion.
- Course-grade visibility and cross-course criterion isolation have explicit regressions.
- `bash dev/qa.sh` passes PHP lint and Moodle PHPCS with zero errors and zero warnings.
## 0.4.0-dev closure results

The guided import closure raises the suite to 95 tests and 369 assertions on every matrix target. Browser coverage includes FP title filtering, ESO band selection, no arbitrary scale default, transparent recommended-scale creation, back navigation and preserved valid state.

Final full PHPUnit matrix after the failed-batch backup regression:

| Moodle | PHP | Database | Tests | Assertions | Result |
|---|---|---|---:|---:|---|
| 4.5.13+ | 8.3.33 | PostgreSQL 16.15 | 95 | 369 | PASS |
| 5.0.9 | 8.3.33 | PostgreSQL 16.15 | 95 | 369 | PASS |
| 5.1.6+ | 8.3.33 | PostgreSQL 16.15 | 95 | 369 | PASS |
| 5.1.6+ | 8.3.33 | MariaDB 11.4.13 | 95 | 369 | PASS |

The complete plugin Behat suite ran on Moodle 5.1.6+ with PostgreSQL and Selenium: 6 features, 18 scenarios and 440 steps passed. It covers separated JSON and guided BOE imports, FP/ESO hierarchy, explicit valuation with no default, transparent scale creation, reversible navigation, Quiz mapping UX, assessment progress, lifecycle and five-language page smokes.

A real Moodle 5.1 upgrade started from the immutable `0.3.0-alpha` artifact at version `2026082702`. Two legacy curricula sharing `RA1.a`, mapped Outcomes, a real grade item/grade, Quiz mappings, assessment/feedback, rubric mapping, checklist response, judgement and feedback-read state survived. Outcome ID 1 remained ID 1 and its label became `RA1.a — Instal·la el sistema operatiu`; grade item outcome ID 1 and final grade `0.75` were unchanged. An external Outcome remained byte-for-byte unchanged. No historical batches were invented.

Clean install and upgraded installations both expose 15 plugin tables, 152 equivalent column definitions, 63 indexes and 15 structural constraints. Added-column physical order differs, which has no schema semantics; names, types, nullability, defaults, keys and indexes match.

Backup/restore was rerun with and without user information on PostgreSQL and MariaDB. Structural provenance, archive and audit history survive both modes. Without user information batch `userid` becomes `NULL`; with user information it is remapped. Failed batches are included as structural audit records and restore without partial items.

Production-style rollback was tested outside PHPUnit. A process failed after several writes and a fresh process/connection verified zero frameworks, parents, criteria, Outcomes and import items on PostgreSQL 16.15 and MariaDB 11.4.13. Exactly one deliberate `failed` audit batch survived.

Live AEBOE semantic regression: `BOE-A-2022-4975`, Tecnología y Digitalización, returns real CE1/CE2 text distinct from 1.1/2.1. `BOE-A-2014-5591`, FP module 3016, returns 6 RA and 44 criteria; RA1 excludes the assessment heading and RA6.h excludes duration, basic contents and pedagogical guidance. `BOE-A-2009-18355` returns HTTP 404 for metadata and text and remains `SOURCE_UNAVAILABLE`. No scraping or undocumented fallback is used.

Language catalogs EN, ES, CA, EU and GL have identical key sets. A browser smoke opens the main, BOE, management, history and student-progress pages in all five languages and rejects missing-string markers. EU/GL require human linguistic review. Moodle's official Valencian locale is `ca_valencia`; no invented locale is shipped.
