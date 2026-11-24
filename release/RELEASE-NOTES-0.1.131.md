# CORE 0.1.131

## Test-world entry selection and feedback

- Hardened browser-side current-System resolution by trimming candidate IDs, ignoring blank values, and preferring a candidate that resolves to an actual generated System record.
- A blank, whitespace-only, or stale legacy `current_system_id` can no longer mask a valid nested `location.system_id` or valid boot location.
- Kept the canonical current-System field authoritative whenever it resolves to a generated System.
- Improved the Test World helper text so its purpose and current-System restriction are clear before selection.
- Improved the enabled-state message to explain that entry opens a temporary test environment and that world coordinates are not yet persisted.
- Existing server-side ownership, lifecycle, universe, System, and generated-World checks remain authoritative.

## Navigation consistency

- Updated internal test descriptions to use the accepted Constellation View → System View navigation terminology. No Universe View was introduced.

## Verification

- PHP project test suite: 124 passed, 0 failed.
- PHP syntax: verified by the project test suite across all PHP files.
- JavaScript syntax: separately checked for the changed gameplay script.
- Browser interaction/visual QA: not performed. The user's next browser test is still needed to confirm the button enables after selecting a World.
