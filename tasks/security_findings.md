## [FINDING] GitHub PAT hardcoded in git remote URL
- **Date**: 2026-08-06
- **Severity**: High
- **Location**: `.git/config` — `remote.origin.url`
- **Type**: Hardcoded Credential
- **Description**: The `origin` remote is configured as `https://ghp_***@github.com/SilverDay/daybreak.git` — a GitHub personal access token embedded directly in the URL. It's stored in plaintext in `.git/config` (readable by anyone with local/shell access) and is echoed in full by any `git remote -v`, `git config -l`, or similar command, including into terminal history and tool logs.
- **Recommendation**: Rotate this token now that it has been displayed in a tool transcript, then reconfigure the remote without embedding a credential in the URL — use SSH (`git@github.com:SilverDay/daybreak.git`) with a deploy key, or a plain HTTPS URL backed by a git credential helper / cached credential store. Never put tokens directly in `remote.url`.
- **Status**: Open — flagged to user, not remediated (requires user's decision to rotate the token and choice of auth method)

## [FINDING] CSRF Empty-Token Acceptance Edge Case
- **Date**: 2026-06-12
- **Severity**: Medium
- **Location**: src/Security/Csrf.php
- **Type**: CSRF Validation Weakness
- **Description**: `Csrf::check()` previously compared the submitted token against `$_SESSION['csrf'] ?? ''`. If no session token existed and an empty token was submitted, `hash_equals('', '')` succeeded.
- **Recommendation**: Require a non-empty server-side token and non-empty submitted token before comparison; reject otherwise.
- **Status**: Fixed
