# /new-migration

Create the next numbered migration in `migrations/`.

## Usage
`/new-migration <short_snake_description>`

## What to generate
1. Find the highest NNN in `migrations/NNN_*.sql`; create `migrations/<NNN+1>_<description>.sql`.
2. Header comment + `SET NAMES utf8mb4;`. InnoDB + utf8mb4 on new tables.
3. Append-only: never edit an applied migration.
4. Remind: run `php migrations/run.php` to apply.
