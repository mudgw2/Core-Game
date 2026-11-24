# CORE 0.1.175 — Universe / Constellation / System Navigation

## Purpose
Restore the intended navigation hierarchy. Gameplay previously opened directly into Constellation View while presenting Universe-level data, and the character roster's orbital preview was mislabeled as a Universe view.

## Changes
- Added a distinct Universe View to gameplay, showing generated Constellations using their deterministic universe-level X/Y coordinates.
- Universe View markers display each Constellation name and system count. Selecting a marker opens that Constellation; selecting a Constellation in the sidebar and using the action button does the same.
- Added an explicit Back to Universe View control from Constellation View.
- Kept the existing System View as a child of Constellation View, with its existing Back to Constellation View navigation.
- Updated the selected-entity panel and sidebar list to show Constellations while in Universe View and Star Systems while in Constellation View.
- Kept the Universe View viewport bounded to the existing map panel; the SVG map owns the Universe-level display rather than exposing a vertically expanding page element.
- Relabeled the character-roster orbital preview from “UNIVERSE / LOCATION” to “SYSTEM / LOCATION”, because it previews the character's current star system and its orbits.
- No persisted universe, constellation, system, or world identifiers or data formats were changed.

## QA
- `php tests/run.php`: 163 passed, 0 failed.
- `node --check player/player.js`: passed.
- PHP syntax checks: passed for changed PHP files; the full PHP syntax suite also passed.
- Browser visual/runtime verification is not available in this build environment and remains required after installation.
