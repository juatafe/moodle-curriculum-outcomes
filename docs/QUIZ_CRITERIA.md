# Quiz criteria architecture

## Identity and scope

The mapping identity is `quiz_slots.id -> local_crout_criterion.id`, with `quiz.id` retained for validation, efficient lookup, and database integrity. It is deliberately not based on `question.id`: a bank entry can be reused, versioned, configured as always-latest, or selected by a random slot while the pedagogical intention belongs to this occurrence in this quiz.

Moodle 4.5–5.1 stores the stable occurrence in `quiz_slots`. The public `mod_quiz\structure` API exposes those records through `get_slots()`. Normal slots use `question_references`; random/set slots use `question_set_references`. Neither changes the plugin mapping because both references identify the question source for a `quiz_slots.id`.

For a random slot, every question selected into that slot contributes to the criteria mapped to the slot. Teachers must therefore ensure that the random set is curriculum-homogeneous. The plugin does not infer criteria from selected questions.

## Aggregation

Each quiz/criterion pair has one explicit method:

- `mean`: arithmetic mean of the final question fractions. Mapping weights are ignored.
- `weightedmean`: sum of `fraction × mapping weight` divided by the sum of mapping weights. Weights are positive relative decimals and never need to total 100.

The UI calls this **Question contribution weight** and hides it until the question→criterion checkbox is selected. It controls only that question's contribution to that criterion in one quiz attempt. It is separate from curriculum criterion weight and Moodle's `quiz_slots.maxmark`.

The aggregation selector is shown only after two or more questions have been saved against the same criterion. Unmapped and single-question criteria keep the internal mean default without asking the teacher an unnecessary question. Quiz aggregation combines mapped questions within one attempt; it is not overall criterion achievement. No result is written to an Outcome grade item or current judgement.

## Attempts and question states

`quiz_attempts.uniqueid` identifies a `question_usage_by_activity`. The evidence service loads it with `question_engine::load_questions_usage_by_activity()`, then obtains each mapped occurrence using the slot number stored in the corresponding `quiz_slots` row. Fractions come from the Question Engine API.

`needsgrading`, unfinished, and invalid question states make the criterion result incomplete. In particular, an essay awaiting manual grading is never converted to zero. A finished unanswered/gave-up question follows Moodle's zero-contribution grading semantics and remains visible with its state. Each quiz attempt is reported independently; there is no aggregation between attempts.

## Versions and random questions

Specific-version and always-latest references can change the concrete `question.id` loaded for an attempt without changing `quiz_slots.id`, so mappings survive question version changes. Likewise, a random attempt can load different concrete questions while retaining the mapped slot number.

## Backup and restore

Quiz module backup stores configuration and mappings beside the activity. Moodle restores `quiz_slots` and publishes the real `quiz_question_instance` mapping from old `quiz_slots.id` to the new ID. The local plugin uses that mapping plus its criterion mapping when restoring into a new course. Restore merge remains experimental.

## Deliberate exclusions

This development iteration does not choose a best/latest attempt, combine evidence across activities, convert percentages to Outcome scales, or write `grade_grades`. A future conversion policy must be explicit, configurable, visible, and reversible.
