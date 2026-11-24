# CORE 0.1.150 — Planetary Longitude Movement Correction

## Changes

- The initial player heading at the 0° latitude / 0° longitude spawn is now derived from the planet's local eastward tangent, rather than the elevated camera view vector. This prevents the initial forward direction from inadvertently following the north/south axis and producing latitude-only movement.
- A/D continue to turn around the local surface normal, and W/S continue to move along the character's transported surface heading.
- The `[` and `]` time controls now recognize both `event.key` and `event.code` (`BracketLeft` / `BracketRight`) to tolerate keyboard-layout differences.

## Validation

- PHP regression suite: 140 passed, 0 failed.
- PHP syntax: passed.
- JavaScript syntax: passed.
- Browser visual/runtime QA: not performed.

## Installation

Apply this patch over the existing CORE 0.1.149 installation. It contains only the changed gameplay script, centralized version, regression tests, and release notes. Back up the installation before applying it. No player or universe data files are included.
