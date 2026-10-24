# CORE PHP Foundation 0.1.20 — Character Status & Travel Dashboard

## Changes
- Added a character status dashboard to the browser Workbench.
- Shows selected character and role, current system, character revision, travel status, completed/total jumps, and next jump.
- Separately reports character-visited systems and universe-wide `system_explored` projection entries.
- Dashboard refreshes when selecting/loading characters, after manual movement, after each travel action, and after a universe exploration event is appended.
- Added two browser QA regressions for dashboard field wiring and persisted dashboard source-data updates.
- Updated application health and QA version metadata to 0.1.20.

## Verification performed for this package
- PHP syntax checks: passed.
- Existing CLI QA runner: 9 passed, 0 failed.
- Browser QA endpoint using a local PHP server: 49 passed, 0 failed.
- JavaScript syntax check (`node --check`): passed.
- ZIP integrity check: performed after packaging.

## Install note
Back up your current CORE folder before installing. Preserve existing `storage/data/` and `storage/logs/` contents; do not overwrite or delete user data/logs when updating the code.
