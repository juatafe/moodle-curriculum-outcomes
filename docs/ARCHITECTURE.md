# Architecture — 0.3.0-alpha: Assessment and Feedback

## Overview

0.3.0-alpha extends Quiz criterion evidence (0.2) into a formative assessment system. The curriculum criterion remains the evaluable unit. RA and CE remain grouping containers.

## Core Principle: Separation of Concerns

Six concepts are strictly separated:

1. **Evidence** — A source of information about a criterion (quiz attempt, assignment, rubric, checklist, manual entry)
2. **Assessment** — A teacher's evaluation of evidence (value, feedback, or both)
3. **Instrument** — The tool used for assessment (direct scale, rubric, checklist)
4. **Feedback** — Qualitative commentary (may exist without any grade/value)
5. **Value** — A scale-level selection (may exist without feedback)
6. **Course Grade** — The official Moodle gradebook grade (never modified by this plugin)

## Evidence Sources

| Source Type | Storage | Reference |
|---|---|---|
| `native_outcome` | Moodle core grade_grade | gradeitemid + userid |
| `quiz_criterion` | Moodle Question Engine (0.2) | quizid + slotid + attemptid |
| `direct` | Plugin local_crout_assessment | assessmentid |
| `rubric` | Moodle gradingform_rubric_fillings + plugin mapping | instanceid + rubriccriterionid |
| `checklist` | Plugin `local_crout_checklist_resp` | responseid |

Evidence is NEVER duplicated. When Moodle already stores the data (grade_grade, rubric filling), we reference it. When Moodle cannot represent our data (multi-attempt quiz criterion aggregates, checklist responses), we store it ourselves.

## Assessment Modes

Three modes, stored as constants in `assessment_service`:

```
FEEDBACK_ONLY  = 'feedback_only'   // Feedback text, no scale value
VALUE_ONLY     = 'value_only'      // Scale value, no feedback required
VALUE_AND_FEEDBACK = 'value_and_feedback' // Both
```

## Assessment Status

```
DRAFT    = 'draft'     // Not visible to students
RELEASED = 'released'  // Visible to students
```

Students ONLY see RELEASED assessments. DRAFT assessments are completely hidden.

## Database Schema

### New tables (0.3.0)

#### `local_crout_assessment`

Central assessment record. One row per teacher evaluation of one evidence for one student.

| Field | Type | Nullable | Description |
|---|---|---|---|
| id | int(10) PK | NOT NULL | Auto-increment |
| courseid | int(10) | NOT NULL | FK → course.id |
| criterionid | int(10) | NOT NULL | FK → local_crout_criterion.id |
| userid | int(10) | NOT NULL | FK → user.id (student) |
| sourcetype | char(20) | NOT NULL | native_outcome/quiz_criterion/direct/rubric/checklist |
| sourceid | int(10) | NOT NULL | Reference to source (gradeitemid/quizid/0/instanceid/definitionid) |
| sourceinstanceid | int(10) | NULL | Secondary reference (attemptid/rubricinstanceid/itemid) |
| assessmentmode | char(20) | NOT NULL | feedback_only/value_only/value_and_feedback |
| value | number(10,5) | NULL | Direct assessment numeric value |
| scalevalue | int(10) | NULL | Scale position (1-based) for outcome scale |
| feedback | longtext | NULL | Feedback text |
| feedbackformat | int(10) | NOT NULL | FORMAT_PLAIN/FORMAT_HTML/FORMAT_MOODLE |
| instrumenttype | char(20) | NULL | direct/rubric/checklist |
| instrumentinstanceid | int(10) | NULL | Reference to instrument instance |
| status | char(10) | NOT NULL | draft/released |
| graderid | int(10) | NOT NULL | FK → user.id (teacher) |
| timecreated | int(10) | NOT NULL | Unix timestamp |
| timemodified | int(10) | NOT NULL | Unix timestamp |
| timepublished | int(10) | NULL | Unix timestamp when released |

Keys:
- PRIMARY KEY (id)
- FOREIGN KEY (courseid) → course(id)
- FOREIGN KEY (criterionid) → local_crout_criterion(id)
- FOREIGN KEY (userid) → user(id)
- FOREIGN KEY (graderid) → user(id)
- INDEX (criterionid, userid) — for student view queries
- INDEX (courseid, criterionid) — for course-level queries
- INDEX (sourcetype, sourceid) — for source lookups

#### `local_crout_rubricmap`

Maps rubric dimensions (criteria) to curriculum criteria.

| Field | Type | Nullable | Description |
|---|---|---|---|
| id | int(10) PK | NOT NULL | Auto-increment |
| courseid | int(10) | NOT NULL | FK → course.id |
| rubriccriterionid | int(10) | NOT NULL | FK → gradingform_rubric_criteria.id |
| curriculumcriterionid | int(10) | NOT NULL | FK → local_crout_criterion.id |
| weight | number(10,5) | NULL | Dimension weight within rubric |
| timecreated | int(10) | NOT NULL | |
| timemodified | int(10) | NOT NULL | |

Keys:
- PRIMARY KEY (id)
- UNIQUE (rubriccriterionid, curriculumcriterionid)
- FOREIGN KEY (courseid) → course(id)
- FOREIGN KEY (curriculumcriterionid) → local_crout_criterion(id)

#### `local_crout_checklist_def`

Checklist definitions.

| Field | Type | Nullable | Description |
|---|---|---|---|
| id | int(10) PK | NOT NULL | Auto-increment |
| courseid | int(10) | NOT NULL | FK → course.id |
| name | char(255) | NOT NULL | Checklist name |
| description | text | NULL | Description |
| descriptionformat | int(10) | NOT NULL | FORMAT_PLAIN |
| itemmode | char(10) | NOT NULL | binary/three_state |
| timecreated | int(10) | NOT NULL | |
| timemodified | int(10) | NOT NULL | |

Keys:
- PRIMARY KEY (id)
- FOREIGN KEY (courseid) → course(id)

#### `local_crout_checklist_item`

Checklist items.

| Field | Type | Nullable | Description |
|---|---|---|---|
| id | int(10) PK | NOT NULL | Auto-increment |
| definitionid | int(10) | NOT NULL | FK → local_crout_checklist_def.id |
| name | text | NOT NULL | Item text |
| sortorder | int(10) | NOT NULL | DEFAULT 0 |
| weight | number(10,5) | NULL | Item weight within checklist |
| timecreated | int(10) | NOT NULL | |
| timemodified | int(10) | NOT NULL | |

Keys:
- PRIMARY KEY (id)
- FOREIGN KEY (definitionid) → local_crout_checklist_def(id)

#### `local_crout_checklist_map`

Maps checklist items to curriculum criteria.

| Field | Type | Nullable | Description |
|---|---|---|---|
| id | int(10) PK | NOT NULL | Auto-increment |
| itemid | int(10) | NOT NULL | FK → local_crout_checklist_item.id |
| criterionid | int(10) | NOT NULL | FK → local_crout_criterion.id |
| timecreated | int(10) | NOT NULL | |

Keys:
- PRIMARY KEY (id)
- UNIQUE (itemid, criterionid)
- FOREIGN KEY (itemid) → local_crout_checklist_item(id)
- FOREIGN KEY (criterionid) → local_crout_criterion(id)

#### `local_crout_checklist_resp`

Checklist responses per user per checklist.

| Field | Type | Nullable | Description |
|---|---|---|---|
| id | int(10) PK | NOT NULL | Auto-increment |
| definitionid | int(10) | NOT NULL | FK → local_crout_checklist_def.id |
| itemid | int(10) | NOT NULL | FK → local_crout_checklist_item.id |
| userid | int(10) | NOT NULL | FK → user.id |
| state | char(10) | NOT NULL | not_done/partial/done |
| feedback | text | NULL | Per-item feedback |
| feedbackformat | int(10) | NOT NULL | FORMAT_PLAIN |
| graderid | int(10) | NOT NULL | FK → user.id |
| timecreated | int(10) | NOT NULL | |
| timemodified | int(10) | NOT NULL | |

Keys:
- PRIMARY KEY (id)
- UNIQUE (definitionid, itemid, userid)
- FOREIGN KEY (definitionid) → local_crout_checklist_def(id)
- FOREIGN KEY (itemid) → local_crout_checklist_item(id)
- FOREIGN KEY (userid) → user(id)

#### `local_crout_judgement`

Manual current judgement per criterion per student. Completely optional.

| Field | Type | Nullable | Description |
|---|---|---|---|
| id | int(10) PK | NOT NULL | Auto-increment |
| courseid | int(10) | NOT NULL | FK → course.id |
| criterionid | int(10) | NOT NULL | FK → local_crout_criterion.id |
| userid | int(10) | NOT NULL | FK → user.id (student) |
| scalevalue | int(10) | NULL | Scale position |
| comment | text | NULL | Teacher comment |
| commentformat | int(10) | NOT NULL | FORMAT_PLAIN |
| graderid | int(10) | NOT NULL | FK → user.id (teacher) |
| timecreated | int(10) | NOT NULL | |
| timemodified | int(10) | NOT NULL | |

Keys:
- PRIMARY KEY (id)
- UNIQUE (criterionid, userid)
- FOREIGN KEY (courseid) → course(id)
- FOREIGN KEY (criterionid) → local_crout_criterion(id)
- FOREIGN KEY (userid) → user(id)

#### `local_crout_feedback_read`

Read tracking for released assessments.

| Field | Type | Nullable | Description |
|---|---|---|---|
| id | int(10) PK | NOT NULL | Auto-increment |
| assessmentid | int(10) | NOT NULL | FK → local_crout_assessment.id |
| userid | int(10) | NOT NULL | FK → user.id |
| timeread | int(10) | NOT NULL | Unix timestamp |

Keys:
- PRIMARY KEY (id)
- UNIQUE (assessmentid, userid)
- FOREIGN KEY (assessmentid) → local_crout_assessment(id)
- FOREIGN KEY (userid) → user(id)

### Tables retained from 0.2

- `local_crout_framework` — unchanged
- `local_crout_parent` — unchanged
- `local_crout_criterion` — unchanged
- `local_crout_quizcfg` — unchanged
- `local_crout_quizmap` — unchanged

## Upgrade Path

Version path: `2026082600` (0.2.0-dev) → `2026082701` (0.3.0-dev) → `2026082702` (0.3.0-alpha).

`db/upgrade.php` adds 7 new tables. All existing tables and data are preserved.

## Services

### `evidence_service`

Responsible for collecting evidence from all sources into a unified view.

```
for_criterion(int courseid, int criterionid, int userid): array
  // Returns all evidence for one criterion for one student
  // Sources: native outcomes, quiz (0.2), direct assessments, rubric, checklist

for_attempt(int attemptid): array
  // Returns quiz criterion evidence (delegates to quiz_evidence_service)
```

### `assessment_service`

CRUD for assessments and feedback.

```
save_assessment(array data): int
  // Creates or updates an assessment record
  // Validates: courseid, criterionid, userid, sourcetype, assessmentmode

release_assessment(int assessmentid): void
  // Sets status=released, timepublished=now

draft_assessment(int assessmentid): void
  // Sets status=draft

get_assessments_for_criterion(int courseid, int criterionid, int userid): array
  // Returns all assessments (teacher view, includes drafts)

get_released_assessments(int courseid, int criterionid, int userid): array
  // Returns only released assessments (student view)
```

### `rubric_mapping_service`

Maps rubric dimensions to curriculum criteria.

```
save_mapping(int rubriccriterionid, int curriculumcriterionid, float weight): int
delete_mapping(int mappingid): void
get_mappings_for_rubric(int definitionid): array
get_mappings_for_criterion(int criterionid): array
```

### `checklist_service`

Full CRUD for checklists.

```
create_definition(array data): int
update_definition(int id, array data): void
create_item(int definitionid, string name, int sortorder): int
map_item(int itemid, int criterionid): void
unmap_item(int itemid, int criterionid): void
save_response(int definitionid, int itemid, int userid, string state, string feedback): int
get_definition_with_items(int definitionid): array
get_responses_for_user(int definitionid, int userid): array
```

### `judgement_service`

Manual current judgement.

```
save_judgement(int courseid, int criterionid, int userid, ?int scalevalue, ?string comment, int graderid): int
get_judgement(int criterionid, int userid): ?array
delete_judgement(int judgementid): void
```

### `feedback_service`

Read tracking for feedback.

```
mark_read(int assessmentid, int userid): void
get_unread_count(int courseid, int userid): int
get_unread_for_criterion(int criterionid, int userid): int
```

### `student_progress_service`

Builds the student dashboard model.

```
for_student(int courseid, int userid): array
  // Returns: course grade, RA/CE hierarchy, criteria with evidence counts, feedback counts, unread counts

for_student_criterion(int courseid, int criterionid, int userid): array
  // Returns: criterion detail with chronological evidence list
```

## Rubric Integration Strategy

1. Teacher creates assignment with Moodle Advanced Grading (rubric)
2. Admin/teacher maps rubric dimensions to curriculum criteria via `local_crout_rubricmap`
3. When teacher grades with rubric, Moodle stores filling in `gradingform_rubric_fillings`
4. Plugin reads rubric filling via `gradingform_rubric_instance::get_rubric_filling()`
5. Plugin combines rubric dimension result with curriculum criterion mapping
6. Evidence view shows: rubric dimension → selected level → descriptor → remark
7. NEVER use total rubric grade as criterion value

## Checklist Strategy

1. Checklist is plugin-owned (no native Moodle equivalent for our use case)
2. Definition + items stored in plugin tables
3. Items mapped to curriculum criteria via `local_crout_checklist_map`
4. Responses stored per user per item
5. Binary mode: done/not_done. Three-state: not_done/partial/done
6. Checklist aggregation: NONE by default (observation instrument). Optional PERCENTAGE.
7. NEVER auto-convert checklist percentage to Outcome scale

## Privacy API

Privacy provider implements:
- `\core_privacy\local\metadata\provider`
- `\core_privacy\local\request\plugin\provider`
- `\core_privacy\local\request\core_userlist_provider`

Tables with user data:
- `local_crout_assessment` (userid, graderid)
- `local_crout_checklist_resp` (userid, graderid)
- `local_crout_judgement` (userid, graderid)
- `local_crout_feedback_read` (userid)

Export: structured privacy-writer data with assessments, feedback, checklist responses, judgements and read state.
Delete: removes all user-specific records. Does NOT touch core grade_outcomes, grade_items, grade_grades.

## Backup/Restore

### Definitions (always backed up)
- local_crout_checklist_def
- local_crout_checklist_item
- local_crout_checklist_map
- local_crout_rubricmap

### User data (conditional on userinfo setting)
- local_crout_assessment
- local_crout_checklist_resp
- local_crout_judgement
- local_crout_feedback_read

### Mapping strategy
- User IDs: annotated with `annotate_ids('user', 'userid')`, mapped via `get_mappingid('user', ...)`
- Quiz mappings: restored from Moodle's `quiz_question_instance` slot mapping (0.2 infrastructure).
- Rubric dimensions: restored from Moodle's `gradingform_rubric_criterion` mapping.

## Security

### Capabilities

| Capability | Description | Admin | Editing Teacher | Non-editing Teacher | Student |
|---|---|---|---|---|---|
| `local/criteriaoutcomes:view` | View management pages | Yes | Yes | No | No |
| `local/criteriaoutcomes:import` | Import curriculum | Yes | Yes | No | No |
| `local/criteriaoutcomes:manage` | Manage assessments/instruments and view students | Yes | Yes | No | No |
| `local/criteriaoutcomes:mapquiz` | Map Quiz slots | Yes | Yes | No | No |
| `local/criteriaoutcomes:viewquizevidence` | View Quiz evidence | Yes | Yes | No | No |

The student progress endpoint separately permits an enrolled, non-guest user to see only their own released evidence; another user's ID requires course management permission.

### Validation

Every service method validates:
- courseid exists and user has course context
- criterionid belongs to course
- userid is enrolled in course
- Source references are valid
- No cross-course leakage

## What This Plugin Does NOT Do (0.3 scope)

- NO automatic final grade calculation by competency
- NO automatic RA/CE aggregation
- NO automatic percentage → outcome scale conversion
- NO automatic rubric total → criterion value
- NO automatic checklist percentage → outcome value
- NO modification of Moodle course grade
- NO modification of grade_grades (except via native Outcome flow already in 0.2)
- NO push notifications
- NO AI recommendations
- NO longitudinal history aggregation
## 0.4 curriculum source and import boundary

Providers return the same normalized curriculum shape. Stable `curriculumkey` and per-parent/per-criterion `sourcekey` values separate source identity from mutable display text. A canonical checksum makes unchanged reimports idempotent, while provenance records the provider, source reference, parser version and retrieval metadata without claiming facts the source did not provide.

`import_service` owns the curriculum transaction. The audit batch is created first with `failed` status; framework, parent, criterion, Outcome mapping and import-item writes then run in one Moodle delegated transaction. Success updates the batch atomically with the import. Failure delegates rollback to Moodle, so no curricular or partial item state survives; the pre-existing failed batch remains intentionally as audit history.

Moodle delegated transactions are logical nesting, not independent savepoints per service. In PostgreSQL PHPUnit, `advanced_testcase` normally owns an outer transaction. Tests that must observe physical rollback use `preventResetByRollback()` so the service transaction is outermost. Manual compensating deletes or snapshot restoration must not be added.

Deletion and undo are conservative. Every mutation revalidates current server-side usage. Plugin-owned unused entities may be deleted; academic use forces archive; external or cross-course Outcomes are blocked. Undo compares the current snapshot with the batch snapshot and never overwrites later changes.

Native Outcome labels are presentation data stored as `code — text`. Ownership comes from the plugin criterion mapping, never from shortname coincidence. Upgrade migration updates only demonstrated plugin-owned Outcomes and preserves their IDs and all core grade references.
