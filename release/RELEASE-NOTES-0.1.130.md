# CORE 0.1.130

## Test-world entry validation

- Fixed active System resolution when `current_system_id` exists but is empty or whitespace. The server now falls back to `location.system_id`, matching the browser-side resolution behavior.
- Kept a non-empty canonical `current_system_id` authoritative.
- Test-world entry continues to verify character ownership, lifecycle eligibility, assigned/active universe access, current-System identity, and that the requested World is generated within that System.
- Added regression coverage for empty, whitespace, and conflicting current-System values.

## Gameplay sidebar hierarchy

- Moved the Universe Data card to the first visible position at the top of the left sidebar.
- The sidebar now begins at the Universe level before the selected System and World details, reflecting Universe → Constellation → System → World.

## Verification

- PHP project test suite: 124 passed, 0 failed.
- PHP syntax: passed for all changed PHP files.
- JavaScript syntax: passed for the gameplay, System View, and test-world scripts.
- Browser interaction/visual QA: not performed; server-side entry and layout order are covered by automated regression tests.
