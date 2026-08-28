# Curriculum Outcomes

`local_criteriaoutcomes` is a development Moodle plugin that imports curriculum criteria as native course Outcomes, maps Quiz slots and native rubric dimensions to criteria, supports direct formative assessment and checklists, and gives students a released-evidence progress view.

It does **not** modify Moodle core or `mod_quiz`, infer aggregation between attempts or activities, convert percentages to Outcome scales, or copy rubric totals into criterion values. Normal activity grades, course grades and Outcome grades remain independent.

## Requirements

- Moodle 4.5, 5.0 or 5.1.
- PHP and database versions supported by the selected Moodle release.
- Moodle Outcomes enabled.

## Installation

Install `local_criteriaoutcomes-0.4.0-dev.zip` at **Site administration → Plugins → Install plugins**, or extract it to `local/` (Moodle 4.5/5.0) or `public/local/` (Moodle 5.1). Complete **Site administration → Notifications**. Earlier artifacts remain immutable historical builds.

## Quiz criterion evidence

Open **Curriculum Outcomes → Quiz criteria mapping**, select a course Quiz, then assign each displayed slot to zero, one, or several course criteria. A question contribution weight appears only after that mapping is selected; it is independent of curriculum weights and the question `maxmark`. The mean/weighted-mean choice appears only when two or more questions are already mapped to the same criterion.

The evidence page shows each mapped question's Moodle fraction/state, explicit weight, formula, and result for one attempt. Essays awaiting manual grading produce `Pending`, not zero. Random mappings belong to the slot, so every question in the random set must be pedagogically coherent. See [`docs/QUIZ_CRITERIA.md`](docs/QUIZ_CRITERIA.md).

## Enable Outcomes

Enable **Site administration → Grades → General settings → Enable outcomes**. If disabled, the plugin shows a prerequisite notice and does not import.

## Import curriculum

The course home separates import, management, assessment/mappings and the current curriculum. JSON upload/paste has its own **Import from JSON** page; BOE remains a separate flow. Teachers preview, consciously select a global or course scale, import, manage/archive criteria, inspect history and request a conservative undo. Reimporting unchanged data is idempotent. An unrelated Outcome with the same shortname is never adopted or modified.

Flat native Moodle selectors show plugin-owned criteria as `RA1.a — criterion text` or `1.1 — criterion text`. Existing plugin-owned Outcomes are migrated in place; Outcome IDs, grade items and grades are preserved.

CLI equivalent:

```bash
php local/criteriaoutcomes/cli/import.php --courseid=123 --file=curriculum.json --scaleid=4
```

With Moodle 5.1 the script is below `public/local/`.

## JSON format

See [`examples/curriculum.json`](examples/curriculum.json). `metadata.name`, a non-empty `resultados` array, and a non-empty `criterios` array per RA/CE are expected. Criterion codes must be unique. Weights are optional non-negative metadata; they are not calculated or required to total 100.

## Legacy format

Legacy names prefixed by their codes, such as `RA1.a: Text`, are accepted and normalized without the duplicated prefix.

## Scales

The selected scale must be visible in the course and is never silently preselected. Teachers may explicitly create a course-local recommended ordinal scale: **0–10** or **Achievement (5 levels)**. Creation is idempotent, never adopts an external same-name scale and persists labels in the active language. These scales assess criteria; they do not change activity/course grades or gradebook aggregation. See [`docs/SCALES.md`](docs/SCALES.md).

## Import lifecycle and safety

Imports use stable curriculum/source identities, canonical checksums, provenance, batches and per-criterion audit items. Preview exposes additions, changes, conflicts and removals before any mutation. Active views exclude archived criteria unless **Show archived** is requested.

Hard deletion is allowed only when a plugin-owned criterion has no academic data. Grade items, grades, feedback, assessments or other academic use force archive. External Outcomes and cross-course records are never modified or deleted. Mutation endpoints use POST, sesskey and server-side revalidation. Undo never overwrites a newer change.

A failed import rolls back curriculum, mapping and item writes. One `failed` batch is deliberately retained outside the curriculum transaction as audit history, with no partial import items.

## BOE provider

The official AEBOE consolidated API path is implemented and tested with controlled fixtures. FP selection preserves **BOE → qualification/title → module → RA → criterion**; ESO preserves **BOE → course band → subject → CE → criterion** when the source states the band deterministically. Live extraction is verified for ESO (`BOE-A-2022-4975`) and FP (`BOE-A-2014-5591`). CE text comes from the semantic competency section, while FP assessment headings and later duration/content/guidance sections are excluded.

## Evidence report

Edit an activity and select one or more criteria in Moodle's native Outcomes section. The plugin report lists activities whose `grade_items.outcomeid` points to each mapped Outcome. It neither creates grades nor copies the activity grade.

## Backup and restore

Course backup always includes plugin-owned curriculum, provenance, archive state, instrument definitions and import audit batches/items. Failed batches are retained as audit history; correctly failed imports contain no partial items. With user information disabled, batch user IDs restore as `NULL` and assessments, checklist responses, judgements and feedback-read markers are excluded. With user information enabled those records and batch users are restored through Moodle mappings. Restore as a new course is tested. Restore merge into an existing course remains unsupported.

## Uninstall behaviour

Moodle removes all 15 plugin-owned `local_crout_*` tables, including personal assessment and feedback data. The plugin deliberately does not delete core academic records: native Outcomes, grade items and grades remain.

## Permissions

- Administrator and manager: view/import/manage when their standard permissions allow it.
- Editing teacher: view/import/manage by default.
- Non-editing teacher: no management capabilities by default.
- Student: may view only their own released progress and feedback; draft assessments remain hidden.

Every mutating web request also requires login, course context, import capability, Moodle form/session handling, and a valid sesskey.

## Compatibility

### Designed for

- Moodle 4.5
- Moodle 5.0
- Moodle 5.1

### Tested on

- Moodle 4.5.13+, PHP 8.3.33, PostgreSQL 16.15.
- Moodle 5.0.9, PHP 8.3.33, PostgreSQL 16.15.
- Moodle 5.1.6+, PHP 8.3.33, PostgreSQL 16.15 and MariaDB 11.4.

Tests use valid non-default table prefixes. Moodle prefixes may contain lowercase letters and underscores; a prefix containing digits is rejected by Moodle itself.

## Known limitations

- Development build: complete controlled external manual QA before production use.
- Restore merge into an existing course is not supported as a guaranteed workflow.
- Assignment remains the native Outcome reference module. Quiz criterion evidence is a separate, tested slot-based integration and does not alter native Outcome controls.
- The live BOE path is not complete for historical FP rules unavailable through the consolidated API.
- EU and GL catalogs are complete but still require review by competent human translators.
- No automatic achievement calculation, cross-evidence weighting or longitudinal analytics.

See [`docs/TESTING.md`](docs/TESTING.md) for reproducible QA and [`CHANGES.md`](CHANGES.md) for the release record.

Copyright © Juan Bautista Talens Felis. GNU GPL v3 or later; see [`LICENSE`](LICENSE).
