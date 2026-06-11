# Rule: PHP security (applies to all *.php)

- `declare(strict_types=1);` is the first line of every PHP file. No exceptions.
- Validate input on an allow-list basis; cast/whitelist before use.
- Never `eval`, never `extract($_*)`, never variable-variables on input.
- Throw on unexpected state; let the front controller convert to a 500. Don't leak
  exception messages or stack traces to the client (APP_DEBUG=false in prod).
- Random tokens: `random_bytes`/`bin2hex`. Compare secrets with `hash_equals`.
- Passwords: `Daybreak\Security\Password` (Argon2id) only.
