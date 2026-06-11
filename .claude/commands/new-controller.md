# /new-controller

Scaffold a controller in `src/Controller/` following project conventions.

## Usage
`/new-controller <Name> [requiredRole]`

## What to generate
1. `src/Controller/<Name>Controller.php` — `declare(strict_types=1);`, namespace
   `Daybreak\Controller`. Methods take `array $args`.
2. If `requiredRole` given, call the auth guard first; on POST call `Csrf::check()` next.
3. Read via `Database::query()`; render via template includes (set `$title`, `$activeNav`).
4. Show the route lines to add to `public/index.php` (do NOT overwrite the file).
5. Escape all output with `Html::e()`.
