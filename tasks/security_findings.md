## [FINDING] CSRF Empty-Token Acceptance Edge Case
- **Date**: 2026-06-12
- **Severity**: Medium
- **Location**: src/Security/Csrf.php
- **Type**: CSRF Validation Weakness
- **Description**: `Csrf::check()` previously compared the submitted token against `$_SESSION['csrf'] ?? ''`. If no session token existed and an empty token was submitted, `hash_equals('', '')` succeeded.
- **Recommendation**: Require a non-empty server-side token and non-empty submitted token before comparison; reject otherwise.
- **Status**: Fixed
