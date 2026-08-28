# Recommended scales

The plugin lists every existing global or course Moodle scale without hiding or recommending unrelated defaults. It never preselects a scale.

Each recommended card states whether it is only an **Available template** or already **Available in this course**, and displays its levels before creation. **Create and select** returns the owned scale ID and immediately selects it in the active import form. Repeating the action returns the same scale.

An editing teacher may explicitly create either recommended course-local template:

- **Curriculum Outcomes — 0–10**: ordinal items `0` through `10`.
- **Curriculum Outcomes — Achievement (5 levels)**: localized values from insufficient through excellent.

These are Moodle ordinal scales, not numeric grade fields. They do not calculate means, convert Quiz results, alter activity grades, alter the course/module grade or change gradebook aggregation.

Ownership uses a stable versioned marker plus the course ID, not the display name. Repeating creation returns the same scale. An external same-name scale is neither adopted nor modified. Existing owned scales are never silently rewritten, including after academic use.

Labels are persisted in the active language when the scale is created. Changing language later does not translate or mutate an existing scale. EN, ES, CA, EU and GL template strings are included; EU and GL still require competent human linguistic review.
