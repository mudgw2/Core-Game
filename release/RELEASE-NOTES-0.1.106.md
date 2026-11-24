# CORE 0.1.106 — Borderless Interface Pass

## Changes
- Applied a project-wide border reset to the shared player, gameplay-world, system-view, and workbench stylesheets.
- Removed visible borders from panels, cards, navigation, buttons, form controls, tables, status messages, HUD overlays, and other interface elements across those surfaces.
- Applied the same reset to the standalone player-account administration page, which uses its own embedded stylesheet.
- Preserved keyboard accessibility by retaining visible `:focus-visible` outlines; the border reset does not suppress focus outlines.
- Existing component backgrounds, spacing, typography, and status colors continue to distinguish interface regions without decorative borders.
- Added CLI and browser QA checks for stylesheet coverage and keyboard focus indicators.

## Verification
- CLI test suite: 104 passed, 0 failed.
- Browser QA endpoint: 154 passed, 0 failed (HTTP 200).
- PHP syntax: 94 files passed.
- JavaScript syntax: unchanged; no JavaScript files modified.
- Browser visual testing: not performed.

## Data and compatibility
- No storage schema, player data, account data, or universe data changes.
- No migration required.
- No player records, universe records, credentials, backups, or logs are included.
