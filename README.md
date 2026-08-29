# Curriculum Outcomes

`local_criteriaoutcomes` is a Moodle plugin that imports curriculum criteria as native course Outcomes, maps Quiz slots and native rubric dimensions to criteria, supports direct formative assessment and checklists, and gives students a released-evidence progress view.

It does **not** modify Moodle core or `mod_quiz`, infer aggregation between attempts or activities, convert percentages to Outcome scales, or copy rubric totals into criterion values. Normal activity grades, course grades and Outcome grades remain independent.

## Requirements

- Moodle 4.5, 5.0 or 5.1.
- PHP and database versions supported by the selected Moodle release.
- Moodle Outcomes enabled (**Site administration → Grades → General settings → Enable outcomes**).

## Installation

Install `local_criteriaoutcomes-0.4.0-alpha.zip` at **Site administration → Plugins → Install plugins**, or extract it to `local/` (Moodle 4.5/5.0) or `public/local/` (Moodle 5.1). Complete **Site administration → Notifications**.

## Quick start

1. Install the plugin and complete notifications.
2. Enable Moodle Outcomes if not already enabled.
3. Open a course.
4. Navigate to **Curriculum Outcomes** in the course navigation.
5. Import an official curriculum (BOE) or upload a JSON file.
6. Choose a criterion assessment scale (Achievement 5-level, 0-10, or an existing Moodle scale).
7. Preview the criteria to import.
8. Confirm the import.
9. Map activities or Quiz questions to criteria.
10. Assess evidence, publish feedback, and track student progress.

## Import from BOE

The guided import separates source, curriculum, valuation and review. FP selection preserves **BOE → qualification/title → module → RA → criterion**; ESO preserves **BOE → course band → subject → CE → criterion** when the source states the band deterministically. Every pre-confirmation step has its own back action; valid choices are retained while changing an upstream choice invalidates its derived preview.

Live extraction is verified for ESO (`BOE-A-2022-4975`) and FP (`BOE-A-2014-5591`).

## Import from JSON

Upload or paste JSON at **Curriculum Outcomes → Import from JSON**. See [`examples/curriculum.json`](examples/curriculum.json). `metadata.name`, a non-empty `resultados` array, and a non-empty `criterios` array per RA/CE are expected. Criterion codes must be unique. Weights are optional non-negative metadata; they are not calculated or required to total 100.

CLI equivalent:

```bash
php local/criteriaoutcomes/cli/import.php --courseid=123 --file=curriculum.json --scaleid=4
```

With Moodle 5.1 the script is below `public/local/`.

## Scales

The valuation choice is never silently preselected. Teachers choose **Achievement (5 levels)**, **0-10**, or an existing Moodle scale as an advanced option. Recommended course-local scales are created or reused transparently. Creation is idempotent, never adopts an external same-name scale and persists labels in the active language. These scales assess criteria; they do not change activity/course grades or gradebook aggregation. See [`docs/SCALES.md`](docs/SCALES.md).

## Quiz criteria mapping

Open **Curriculum Outcomes → Quiz criteria mapping**, select a course Quiz, then assign each displayed slot to zero, one, or several course criteria. A question contribution weight appears only after that mapping is selected; it is independent of curriculum weights and the question `maxmark`. The mean/weighted-mean choice appears only when two or more questions are already mapped to the same criterion.

The evidence page shows each mapped question's Moodle fraction/state, explicit weight, formula, and result for one attempt. Essays awaiting manual grading produce `Pending`, not zero. Random mappings belong to the slot, so every question in the random set must be pedagogically coherent. See [`docs/QUIZ_CRITERIA.md`](docs/QUIZ_CRITERIA.md).

## Student progress

Students see their own released progress with RA/CE hierarchy, sibling criteria, evidence counts, feedback and unread state. Official Moodle grades continue to come from the Gradebook independently. See [`docs/STUDENT_PROGRESS.md`](docs/STUDENT_PROGRESS.md).

## Evidence report

Edit an activity and select one or more criteria in Moodle's native Outcomes section. The plugin report lists activities whose `grade_items.outcomeid` points to each mapped Outcome. It neither creates grades nor copies the activity grade.

## Backup and privacy

Course backup always includes plugin-owned curriculum, provenance, archive state, instrument definitions and import audit batches/items. With user information disabled, user-owned assessment data is excluded. With user information enabled, assessments, checklist responses, judgements and feedback-read markers are restored through Moodle mappings. Restore as a new course is tested. Restore merge into an existing course remains unsupported.

The plugin implements the full Moodle Privacy API: metadata, export and deletion for all user data tables (assessments, checklist responses, judgements, feedback read tracking, import attribution).

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

## Known limitations

- This is an alpha release: complete controlled external manual QA before production use.
- Restore merge into an existing course is not supported as a guaranteed workflow.
- Assignment remains the native Outcome reference module. Quiz criterion evidence is a separate, tested slot-based integration and does not alter native Outcome controls.
- The live BOE path is not complete for historical FP rules unavailable through the consolidated API.
- EU and GL catalogs are complete but still require review by competent human translators.
- No automatic achievement calculation, cross-evidence weighting or longitudinal analytics.

## Development and testing

See [`docs/TESTING.md`](docs/TESTING.md) for reproducible QA instructions and [`CHANGES.md`](CHANGES.md) for the release record.

## License

Copyright © Juan Bautista Talens Felis. GNU GPL v3 or later; see [`LICENSE`](LICENSE).

## Issue reporting

Report issues at <https://github.com/juatafe/moodle-curriculum-outcomes/issues>.
