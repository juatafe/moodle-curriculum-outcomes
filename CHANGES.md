# Changelog

## 0.4.0-alpha

First external alpha release prepared for Moodle Marketplace evaluation.

### Highlights

- Real Moodle Outcomes: each imported criterion becomes a native course Outcome.
- JSON curriculum import with mandatory preview and idempotent mapping.
- Official BOE consolidated-API provider for FP, ESO and Bach curricula.
- FP title/module and ESO course-band/subject selection hierarchies.
- Guided import workflow: source, curriculum, valuation, review with reversible navigation.
- Quiz slot-to-criterion mapping with per-criterion aggregation (mean/weightedmean).
- Assessment and feedback system with three modes, draft/release states and rubric integration.
- Checklist instrument with item-criterion mapping and per-item responses.
- Student progress dashboard with RA/CE hierarchy, evidence counts and unread feedback.
- Privacy API: full metadata, export and deletion for all user data.
- Backup/restore: structure, provenance, archive state, import audit and mapped user data.
- EN, ES, CA, EU, GL language catalogs (EU/GL require human linguistic review).
- Tested on Moodle 4.5, 5.0 and 5.1 with PostgreSQL and MariaDB.

### Removed

- Duplicate legacy language string definitions for `coursebandunknown` and `importcurriculum`.

## 0.4.0-dev

- Preserve FP title→module and ESO course-band→subject selection hierarchies from deterministic BOE structure.
- Separate JSON import from the course home and clarify import, management, mapping and current-curriculum tasks.
- Guide imports through source, curriculum, valuation and review, with reversible navigation and state invalidation.
- Make valuation pedagogical and explicit; recommended scales are created/reused transparently without an arbitrary default.
- Clarify Quiz question contribution weight and show aggregation only for criteria with multiple mapped questions, without changing evidence semantics.

### Added

- Provider-neutral normalized curriculum DTO, canonical checksum, parser version, provenance and stable curriculum/source identities.
- Official BOE consolidated-API client and FP/ESO/Bach parsers with SSRF controls and controlled fixtures.
- Import batches/items, selective preview, diff, archive/unarchive, bulk management, safe deletion and conservative undo.
- Course-scoped teacher management, BOE import and import-history flows.
- Backup/restore and Privacy support for provenance, archive state and import audit history.
- Explicit course-local recommended ordinal scale templates (0–10 and localized five-level achievement).
- Complete EU and GL language catalogs; human linguistic review remains pending.

### Changed

- Plugin-owned native Outcomes use `code — text` display labels; the upgrade migrates owned Outcomes in place without changing IDs, grade items or grades.
- Student progress SQL uses criterion ID as the unique Moodle record key and then groups explicitly by parent, preserving sibling RA/CE criteria.
- Failed imports retain one audit batch outside the curricular transaction but roll back all curriculum and partial item writes.
- BOE search metadata is normalized into human-readable result cards and education family is detected only when unambiguous.
- Large previews use accessible collapsible RA/CE groups, criterion counts and select/deselect actions.
- Import controls and previews adapt to narrow viewports without fixed widths or horizontal overflow.

### Fixed

- Removed a temporary manual rollback compensation introduced for a PostgreSQL PHPUnit artefact. Moodle delegated rollback remains authoritative; the regression disables PHPUnit's outer rollback wrapper when physical rollback visibility is required.
- Prevented sibling criteria sharing one parent from being lost by `get_records_sql()` key collisions.
- CE parents now use their real competency text instead of the first criterion, and unsupported missing-competency structures are rejected.
- FP RA headings and final criteria stop at controlled structural section boundaries instead of absorbing later module content.

### Tested

- PHPUnit: 95 tests / 369 assertions on Moodle 4.5.13+, 5.0.9 and 5.1.6+ with PostgreSQL 16; Moodle 5.1.6+ with MariaDB 11.4.
- Behat: 6 features, 18 scenarios and 440 steps on Moodle 5.1.6+.
- Real 0.3.0-alpha → 0.4.0-dev upgrade and clean-install schema equivalence on PostgreSQL 16.
- Production-style failed-import rollback through fresh connections on PostgreSQL 16 and MariaDB 11.4.
- PHP lint and Moodle PHPCS: zero errors and zero warnings.

## 0.3.0-alpha

### Added

- Assessment and feedback system with three modes: feedback-only, value-only, value-and-feedback.
- Assessment status: draft (hidden from students) and released (visible to students).
- Direct criterion assessment with scale values and feedback.
- Rubric integration: maps rubric dimensions to curriculum criteria via `local_crout_rubricmap`.
- Checklist instrument: definitions, items, item-criterion mappings, per-item responses and feedback.
- Current judgement: manual teacher summary per criterion per student.
- Feedback read tracking for released assessments.
- Student progress dashboard ("Mi progreso") with RA/CE hierarchy, evidence counts, unread feedback.
- Criterion drill-down with chronological evidence from all sources.
- Teacher progress view with student selector.
- Privacy API: full metadata, export, and delete for all user data tables.
- 7 new database tables, for 13 plugin-owned tables in total: assessment, rubric mapping, checklist (def/item/map/response), judgement, and feedback read.
- Upgrade from 0.2.0-dev preserves all existing data.

### Changed

- Version promoted to 0.3.0-alpha (2026082702) after the complete compatibility matrix passed.
- Privacy API no longer uses null provider (now stores user assessments, feedback, and read tracking).
- assessment_service validation uses `is_numeric()` for Moodle DML compatibility (IDs may be strings on PostgreSQL).
- Course backup without user information excludes every user-owned record; backup with user information restores assessments, checklist responses, judgements and read markers through Moodle user mappings.
- Native rubric evidence resolves the assessed student through `assign_grades` and keeps each mapped dimension, selected level, score and remark independent.
- Course-grade display uses Moodle's course grade item and honours its hidden state.

### Tested

- PHPUnit: 55 tests / 191 assertions on Moodle 4.5.13+, 5.0.9 and 5.1.6+ with PostgreSQL 16; Moodle 5.1.6+ with MariaDB 11.4.
- Behat: 4 scenarios / 88 steps on Moodle 5.0.9, covering import, Quiz mapping, draft/release visibility and feedback read state.
- PHP lint and Moodle PHPCS: zero errors and zero warnings.

## 0.2.0-dev

### Added

- Quiz-slot-to-criterion mappings with positive relative weights and per-criterion `mean` or `weightedmean` aggregation.
- Traceable per-attempt evidence loaded through Moodle Question Engine APIs, including independent attempts and pending manual grading.
- Support and tests for random slots, always-latest question versions, orphan detection/cleanup, and quiz activity backup/restore with slot remapping.
- Editing-teacher mapping UI, evidence report, module-context capabilities, and EN/ES/CA strings.

### Database and upgrade

- Added `local_crout_quizmap` and `local_crout_quizcfg` with portable XMLDB keys.
- Real upgrade from version `2026082502` preserves existing data and installs version `2026082600`.

### Tested

- PHPUnit: 18 tests / 70 assertions on Moodle 4.5.13+, 5.0.9 and 5.1.6+ with PostgreSQL 16; Moodle 5.1.6+ with MariaDB 11.4.
- Behat mapping workflow: 1 scenario / 26 steps on Moodle 5.0.9.
- PHP lint and Moodle PHPCS: zero errors and zero warnings.

## 0.1.0-alpha

### Added

- JSON upload/paste, mandatory preview, native course Outcome creation and idempotent mappings.
- RA/CE hierarchy, native activity evidence report, CLI import and course backup/restore.
- PHPUnit coverage for validation, conflicts, scale safety, multiple evidence and restoration.

### Changed

- Course criteria alone become Outcomes; weights remain optional metadata and are never calculated.
- Moodle 5.1 split-root and Moodle 4.5/5.0 legacy-root entrypoints are both supported.

### Fixed

- Unrelated Outcomes with the same shortname are reported as conflicts rather than adopted.
- Scale changes cannot reinterpret existing grade items or grades.
- Backup/restore remaps restored core Outcome identifiers.
- Invalid/empty scale API entries are ignored instead of breaking the web page.

### Tested

- Moodle 4.5.13+, 5.0.9 and 5.1.6+ with PHP 8.3 and PostgreSQL 16.
- Moodle 5.1.6+ with PHP 8.3 and MariaDB 11.4.
- Non-default valid database prefixes and the packaged ZIP layout.
- Moodle 5.0 focused Behat: 2 scenarios and 27 browser steps.
