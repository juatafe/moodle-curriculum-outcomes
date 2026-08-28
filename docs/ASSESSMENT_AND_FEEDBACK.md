# Assessment and Feedback — 0.3.0-alpha

## Overview

This document describes the assessment and feedback system released for alpha testing in 0.3.0.

## Assessment Modes

Each assessment can operate in one of three modes:

### FEEDBACK_ONLY

Teacher provides qualitative feedback without assigning a scale value.

Example:
```
RA1.a — Configura sistemas
Feedback: "Revisa la configuración DNS. La resolución no funciona correctamente."
Valor: (ninguna)
```

The student sees feedback but no numeric or scale value. Absence of a value is NOT interpreted as zero.

### VALUE_ONLY

Teacher selects a scale level without writing feedback.

Example:
```
RA1.a — Configura sistemas
Valoración: Adquirido
Feedback: (no requerido)
```

### VALUE_AND_FEEDBACK

Teacher provides both a scale value and feedback.

Example:
```
RA1.a — Configura sistemas
Valoración: En proceso
Feedback: "La configuración funciona, pero falta justificar la elección de DNS."
```

## Assessment Status

### DRAFT

Not visible to the student. Teacher can edit freely.

### RELEASED

Visible to the student. Once released, the teacher can still edit but changes are tracked via timemodified.

Students ONLY see RELEASED assessments. DRAFT assessments are completely invisible to them.

## Evidence Sources

### Native Outcome Evidence

When an activity has an Outcome grade item, the grade_grade in Moodle core is the evidence. The plugin references it but does NOT copy the grade value.

### Quiz Criterion Evidence (0.2)

Quiz slot → criterion mappings produce per-attempt criterion evidence via the Question Engine. This data is read-only from the plugin's perspective.

### Direct Assessment

Teacher directly assigns a scale value to a criterion. Stored in `local_crout_assessment`.

### Rubric Evidence

Moodle's Advanced Grading rubric dimensions are mapped to curriculum criteria. The plugin reads rubric filling data via the grading API.

### Checklist Evidence

Plugin-owned checklist items are mapped to curriculum criteria. Teacher marks items and optionally provides per-item feedback.

## Rubric Integration

### How It Works

1. An activity (e.g., assignment) uses Moodle's rubric grading
2. Admin maps rubric dimensions to curriculum criteria
3. When the teacher grades with the rubric, Moodle stores the filling
4. The plugin reads the filling and presents it as criterion evidence

### What We Store

- Mapping: rubric dimension → curriculum criterion (in `local_crout_rubricmap`)
- Evidence is read from Moodle's native rubric filling; the plugin does not create a duplicate assessment row.

### What We Do NOT Store

- Copies of rubric criteria or levels (these stay in Moodle core)
- The total rubric grade (we never use it as criterion value)

### Rubric Dimension → Curriculum Criterion

A rubric dimension can map to:
- One curriculum criterion (most common)
- Multiple curriculum criteria (if pedagogically necessary)

Multiple rubric dimensions can map to the same curriculum criterion. In that case, the evidence view shows each dimension separately. Aggregation is NOT automatic.

## Checklist

### Definition

A checklist has:
- Name
- Description (optional)
- Item mode: binary (done/not_done) or three-state (not_done/partial/done)

### Items

Each item has:
- Name/text
- Sort order
- Optional weight (within the checklist, not curriculum weight)

### Item → Criterion Mapping

Each item can map to:
- No criterion (observation only)
- One criterion
- Multiple criteria

### Responses

Per user, per item:
- State: not_done / partial / done
- Optional feedback from teacher

### Aggregation

By default: NONE. The checklist is an observation instrument.

Optional future modes:
- PERCENTAGE (completed items / total items)
- WEIGHTED_PERCENTAGE (weighted by item weight)

The checklist percentage is NEVER automatically converted to an Outcome scale value.

## Current Judgement

A manual summary that the teacher can set per criterion per student. Completely optional.

Example:
```
RA1.a — Configura sistemas
Valoración actual: Adquirido
Comentario: "Las últimas prácticas muestran trabajo autónomo."
```

This is:
- Explicitly set by the teacher
- NOT calculated automatically
- Independent of evidence count or values
- May remain empty

## Student View: "Mi Progreso"

The student sees:

1. **Course Moodle grade** — from gradebook, never modified by plugin
2. **RA/CE hierarchy** — with weights
3. **Criteria** — with evidence counts, feedback counts, unread counts
4. **Drill-down** — clicking a criterion shows chronological evidence

### What the Student Sees per Criterion

- Code and name
- Parent RA/CE
- Weight (if defined)
- Current judgement (if set by teacher)
- Evidence list (chronological, most recent first)
- For each evidence: source, date, value/scale, feedback, instrument
- Released feedback only (never drafts)

### What the Student Does NOT See

- DRAFT assessments
- Other students' data
- Internal mappings
- Instrument configuration
- Assessment mode settings

## Moodle Gradebook Integration

### What We Reuse

- `grade_grade.feedback` — for native Outcome evidence feedback
- `grade_grade.finalgrade` — for native Outcome evidence values
- Moodle's course `grade_item` plus its `grade_grade` — for course grade display and hidden-state preservation
- `grade_outcome` — for criterion Outcome linkage (0.2)

### What We Store Ourselves

- Direct assessment records and their released/draft state
- Checklist responses
- Current judgements
- Feedback read tracking

### Why We Don't Just Use Gradebook

Moodle's gradebook has limitations for our use case:
- Outcome items are NOT overridable (can't set feedback on them)
- A single grade_grade doesn't preserve multi-attempt history
- No concept of "assessment mode" (feedback only vs value only)
- No read tracking
- No current judgement

## Backup and Restore

### Definitions (Always Restored)

Checklist definitions, items, item-criterion mappings, rubric-dimension mappings.

### User Data (Conditional on userinfo)

Assessments, checklist responses, judgements, read state.

When restoring WITHOUT userinfo: definitions and mappings are restored, but no student data.

### User ID Mapping

User and grader IDs are remapped using Moodle's standard backup user mapping. In the tested same-site new-course restore, Moodle correctly resolves them to the existing enrolled accounts; restore never treats raw source IDs as destination mappings.

## Privacy

User data stored:
- `userid` in assessments, checklist responses, judgements, read tracking
- `graderid` in assessments, checklist responses, judgements
- Feedback text (may contain personal information)

Export includes all user-specific records. Delete removes only plugin-owned data; core Moodle data (grade_outcomes, grade_items, grade_grades) is preserved.

## Security

- Students see ONLY their own data
- Students see ONLY released assessments and feedback
- Teachers assess ONLY within authorized contexts
- All IDs are validated server-side against real context membership
- No trust in URL-supplied IDs
