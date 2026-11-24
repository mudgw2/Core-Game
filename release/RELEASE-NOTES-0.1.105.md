# CORE 0.1.105 — Animation and Orbital Geometry

## Changes
- Character-selection portraits now select the exact `Walk` animation clip. Broad substring matching was removed because it could select `Crouch_Walk`.
- Added `player/orbital-mechanics.js` as the shared orbital-layout calculation used by both character-selection location previews and the gameplay system view.
- Both displays now use the same orbital-slot ordering, geometrically increasing spacing, and Kepler-style angular-speed scaling.
- Removed invented per-planet orbital-plane inclinations. The current world data defines orbital slots, not individual inclinations or eccentricities, so the views now use a common reference plane rather than fabricating orbital parameters.
- Updated CLI and browser QA coverage for exact animation selection and shared orbital calculations.

## Verification
- CLI test suite: 101 passed, 0 failed.
- Browser QA endpoint: 152 passed, 0 failed (HTTP 200).
- PHP syntax: 94 files passed.
- JavaScript syntax: 3 changed JavaScript files passed `node --check`.
- Orbital helper runtime check: verified strictly increasing orbital radii and positive finite angular speeds for a four-world system.
- No browser visual testing was performed.

## Data and compatibility
- No storage schema or player-record migration.
- No universe, character, account, credential, backup, or log data included.
- The change is presentation-only and reads existing `orbital_slot` world data.
