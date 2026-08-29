# Moodle Marketplace Submission Material

Working document for future submission to Moodle Marketplace.
**DO NOT submit until repository is public and all manual QA is complete.**

---

## Plugin name

Curriculum Outcomes

## Frankenstyle

`local_criteriaoutcomes`

## Short description

Imports curriculum criteria as native Moodle Outcomes, supports Quiz-to-criterion mapping, direct formative assessment, checklists and student progress tracking.

## Full description

Curriculum Outcomes bridges official education curricula and Moodle's assessment ecosystem. It imports curriculum criteria (from official BOE sources or JSON) as native Moodle course Outcomes, enabling teachers to map Quiz questions and rubric dimensions to individual criteria, assess evidence directly, provide feedback, and track student progress against the imported curriculum structure.

The plugin supports the Spanish FP, ESO and Bachillerato curricula through the official BOE consolidated API, as well as arbitrary curricula through a flexible JSON format. It does not modify Moodle core, infer automatic aggregations, or alter the Gradebook. All criterion assessment is teacher-driven and explicitly released to students.

## Key features

- Real Moodle Outcomes: each imported criterion becomes a native course Outcome.
- BOE official curriculum provider for FP, ESO and Bachillerato.
- JSON curriculum import with preview and idempotent mapping.
- Guided import workflow with reversible navigation and state preservation.
- Quiz slot-to-criterion mapping with per-criterion aggregation.
- Assessment and feedback system with three modes and draft/release states.
- Rubric dimension mapping to curriculum criteria.
- Checklist instrument with item-criterion mapping.
- Student progress dashboard with RA/CE hierarchy and evidence counts.
- Import lifecycle: audit batches, diff preview, archive, undo.
- Backup/restore: full structure and mapped user data.
- Privacy API: metadata, export and deletion for all user data.
- Multi-language: EN, ES, CA, EU, GL.

## Requirements

- Moodle 4.5, 5.0 or 5.1.
- Moodle Outcomes must be enabled.
- PHP and database versions supported by the selected Moodle release.

## Supported Moodle versions

- Moodle 4.5 (tested on 4.5.13+)
- Moodle 5.0 (tested on 5.0.9)
- Moodle 5.1 (tested on 5.1.6+)

Tested with PostgreSQL 16.15 and MariaDB 11.4.

## Privacy summary

The plugin implements the full Moodle Privacy API. It stores user assessments, checklist responses, judgements, feedback read tracking and import attribution. All user data can be exported and deleted per-context or per-user. The provider is fully declared with metadata for all personal data tables.

## Backup/restore summary

Course backup includes all plugin-owned curriculum structure, provenance, archive state, instrument definitions and import audit batches/items. With user information enabled, assessments, checklist responses, judgements and feedback-read markers are restored through Moodle user mappings. Restore as a new course is tested. Restore merge into an existing course is not supported.

## Known limitations

- Alpha release: requires external manual QA before production use.
- Restore merge into an existing course is not supported as a guaranteed workflow.
- The live BOE path is not complete for historical FP rules unavailable through the consolidated API.
- EU and GL language catalogs require review by competent human translators.
- No automatic achievement calculation, cross-evidence weighting or longitudinal analytics.

## Source repository

`https://github.com/juatafe/moodle-curriculum-outcomes`

## Issue tracker

`https://github.com/juatafe/moodle-curriculum-outcomes/issues`

## Documentation links

- README: `https://github.com/juatafe/moodle-curriculum-outcomes/blob/main/README.md`
- Architecture: `https://github.com/juatafe/moodle-curriculum-outcomes/blob/main/docs/ARCHITECTURE.md`
- Testing: `https://github.com/juatafe/moodle-curriculum-outcomes/blob/main/docs/TESTING.md`
- Scales: `https://github.com/juatafe/moodle-curriculum-outcomes/blob/main/docs/SCALES.md`
- BOE Provider: `https://github.com/juatafe/moodle-curriculum-outcomes/blob/main/docs/BOE_PROVIDER.md`
- Quiz Criteria: `https://github.com/juatafe/moodle-curriculum-outcomes/blob/main/docs/QUIZ_CRITERIA.md`
- Student Progress: `https://github.com/juatafe/moodle-curriculum-outcomes/blob/main/docs/STUDENT_PROGRESS.md`

## Suggested screenshots

1. **Curriculum Outcomes home** - Course page showing import, management, assessment and current curriculum sections.
2. **Guided BOE import - Source selection** - BOE search results with education family detection.
3. **FP title/module selection** - Qualification and vocational module hierarchy.
4. **ESO band/subject selection** - Course band and subject selection.
5. **Preview RA/CE + criteria** - Collapsible RA/CE sections with criterion counts and select/deselect.
6. **Quiz mapping** - Quiz slot-to-criterion mapping interface.
7. **Student progress** - RA/CE hierarchy with evidence counts and unread feedback.
8. **Criterion assessment/feedback** - Assessment form with mode selection, scale value and feedback.

Note: Screenshots must be actual captures from a running Moodle installation. Do not create placeholder or mock screenshots.
