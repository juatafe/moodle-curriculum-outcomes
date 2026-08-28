# Student Progress — 0.3.0-alpha

## Overview

The student progress system provides transparent, drill-down views of curriculum criterion evidence. It does NOT calculate automatic grades or aggregate criteria.

## Entry Points

### Student: "Mi Progreso"

Available in course navigation for students with `viewownevidence` capability.

### Teacher: "Progreso del alumnado"

Available in course navigation for teachers with `viewallevidence` capability. Includes student selector.

## Student Dashboard Structure

```
COURSE NAME
Nota Moodle: 7,40

────────────────────────

RA1 — Instala sistemas operativos
Peso: 25%

  RA1.a
  Peso: 40%
  Última evidencia: Adquirido
  4 evidencias
  💬 2 feedbacks

  RA1.b
  Peso: 30%
  Valoración actual: En proceso
  3 evidencias
  💬 1 feedback nuevo

  RA1.c
  Peso: 30%
  Última evidencia Quiz: 88%
  5 evidencias
  💬 3 feedbacks

────────────────────────

RA2
...
```

## Data Sources

### Course Grade

Obtained from Moodle Gradebook via `grade_get_grades()`. The plugin NEVER modifies or recalculates this value. Respects hidden grades, visibility settings, and display types.

### RA/CE Weights

From `local_crout_parent.weight`. If NULL, displayed as "Ponderación no definida" or omitted.

### Criterion Weights

From `local_crout_criterion.weight`. Same display policy.

### Evidence Count

Count of released assessments from all sources for the criterion + student.

### Feedback Count

Count of released assessments that contain feedback text.

### Unread Count

Count of released assessments with feedback that the student hasn't read yet.

### Latest Evidence

The most recently published assessment. Displayed as:
- Scale value if VALUE_ONLY or VALUE_AND_FEEDBACK mode
- "Con feedback" if FEEDBACK_ONLY mode
- Source label (Quiz, Rubric, Checklist, etc.)

### Current Judgement

If the teacher has set a manual judgement, it takes prominence over "latest evidence".

## Criterion Drill-Down

Clicking a criterion opens the detail view:

```
RA1.a — Configura sistemas
RA padre: RA1 — Instala sistemas operativos
Peso: 40%

Valoración actual: Adquirido
"Las últimas prácticas muestran trabajo autónomo."

────────────────────────

Evidencias (más reciente primero):

  15/11 — Proyecto servidor
  Instrumento: Rubric
  Dimensión: Documentación
  Nivel: Adquirido
  Descriptor: "Documenta correctamente..."
  Feedback: "Faltan únicamente las pruebas de penetración."

  21/10 — Práctica Ubuntu
  Instrumento: Checklist
  ☑ Configura IP
  ☑ Gateway
  △ DNS
  ☑ Usuarios
  Feedback: "Funciona, pero has dejado el DNS de DHCP."

  03/10 — Quiz tema 1
  Intento 2
  Resultado: 68.75%
  P1: 100% (peso 1)
  P2: 50% (peso 2)
  P4: 75% (peso 1)
  Método: Weighted mean

  18/09 — Práctica inicial
  Instrumento: Direct
  Valoración: En proceso
  Feedback: "Configuras la red, pero falta justificar DNS."
```

## Evidence Display by Source

### Native Outcome

Shows:
- Activity name
- Scale value (from grade_grade)
- Feedback (from grade_grade, if present)
- Date

### Quiz Criterion

Shows:
- Quiz name
- Attempt number
- Per-question fractions and weights
- Aggregation method
- Result percentage
- Associated feedback if any

### Direct Assessment

Shows:
- Activity or context name
- Scale value
- Feedback
- Date

### Rubric

Shows:
- Activity name
- Rubric dimension name
- Selected level
- Descriptor
- Remark/feedback
- Date

### Checklist

Shows:
- Checklist name
- Item states (☑/△/☐)
- Per-item feedback (if released)
- General feedback

## Weight Display Policy

Weights are shown only when they exist:

```
Peso: 40%
```

When weight is NULL:

```
Ponderación no definida
```

The plugin NEVER invents weights (e.g., "33.33%" for 3 criteria).

## What Is NOT Shown

- RA-level aggregated percentage
- Criterion-level aggregated percentage
- "Final state" calculated from evidence
- Predictions or projections
- Other students' data
- DRAFT assessments
- Internal mappings or configuration

## Navigation

1. Course → Mi Progreso (or Progreso del alumnado)
2. RA/CE list with summary cards
3. Click RA → criteria list
4. Click criterion → evidence detail
5. Click evidence → instrument detail (rubric levels, checklist items, quiz questions)

## Performance

- Student dashboard loads aggregated counts efficiently (no N+1)
- Teacher list paginates students
- Evidence detail loaded on demand (not preloaded for all criteria)
- Counts use SQL COUNT, not PHP iteration
