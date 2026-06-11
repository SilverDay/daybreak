# Rule: SQL / data access (Database wrapper)

- All queries go through `Daybreak\Database::query($sql, $params)` with **bound params**.
- NEVER concatenate or interpolate request data into SQL strings.
- Use `ON DUPLICATE KEY` for idempotent upserts (articles keyed on (source_id, guid)).
- Keep controllers thin; put multi-step logic in `src/Service/`.
- Migrations are append-only numbered files; never edit an applied migration — add a new one.
