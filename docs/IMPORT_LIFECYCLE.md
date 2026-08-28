# Import lifecycle

1. A provider parses and normalizes source data.
2. Preview resolves stable curriculum/source identities and classifies new, unchanged, changed, conflicting and removed criteria without mutation.
3. The teacher explicitly chooses a visible Moodle scale and confirms selected source keys. Optionally, they may first create one plugin-owned course-local recommended scale; no external scale is adopted by name.
4. A `failed` import batch is created as the durable audit envelope.
5. Framework, parent, criterion, plugin-owned Outcome mapping and import-item writes execute in one Moodle delegated transaction.
6. Success marks the batch complete. Failure rolls back all curricular and partial item writes while retaining the empty failed batch.

Reimporting an unchanged checksum is idempotent. Removed source criteria are candidates for conservative archive/delete analysis, never implicit destructive deletion. Active lists hide archived criteria by default and expose them through **Show archived**.

Hard delete requires a plugin-owned entity with no grade item, grade, assessment, feedback or other academic dependency. Any academic use means archive only. External and cross-course Outcomes are blocked. Preview is advisory; the final POST+sesskey mutation repeats server-side checks.

Undo is batch-based. Created unused records may be deleted, later-used records are preserved or archived, matched records remain, and updates are restored only when the current snapshot still equals the batch result. Later changes produce `CONFLICTED_UNDO` and are never overwritten.

Do not add manual rollback compensation. Moodle nested delegated transactions defer physical rollback to the outermost owner; PostgreSQL PHPUnit tests commonly supply that outer transaction. A test needing immediate physical visibility must call `preventResetByRollback()`.
