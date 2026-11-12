# CORE 0.1.91 — World Profile Inspection

## Scope

- Made generated worlds selectable in the selected-system panel.
- Added an accessible world-selection control with a visible selected state.
- Added a world profile detail panel for the selected world.
- Displays the complete generated profile: eight-character UWP, world type, orbital slot, starport, size, atmosphere, hydrographics, population, government, law level, and technology level.
- Adds concise descriptive labels for starport, size, atmosphere, population, and law-level values.
- Clearly distinguishes generated descriptive profile data from future landing and surface gameplay.
- Preserves deterministic world generation and does not modify persisted universe or character records.
- Updated central release version and regression tests.

## Compatibility and data safety

No storage migration is required. No existing universe, character, account, inventory, or other player record is rewritten. The patch excludes `storage/`, secrets, logs, backups, and Git metadata.

## Validation performed against this exact patch

- CLI suite: 86 passed, 0 failed.
- Browser QA endpoint: 145 passed, 0 failed (HTTP 200).
- PHP syntax: 88 files passed.
- JavaScript syntax: 5 files passed.
- Player-facing prohibited-term scan: clean.

User installation QA remains pending until confirmed.
