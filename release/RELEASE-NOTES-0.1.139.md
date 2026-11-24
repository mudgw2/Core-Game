# CORE 0.1.139 — Spherical Planetary Environment

## Added
- Replaced the finite rectangular terrain patch with a complete spherical planetary surface rendered by Babylon.js.
- Added deterministic seed-driven global surface generation with ocean coverage informed by world hydrographics.
- Added physical planetary radius, diameter, and circumference metadata derived from the Traveller-style world size code.
- Added spherical latitude/longitude coordinates and surface-following movement across the globe.
- Expanded bounded multiplayer movement coordinates so authoritative player transforms can traverse multiple planetary circumferences.
- Replaced the legacy hex-only map display with a global equirectangular map using the same procedural spherical surface field as the 3D globe.
- Retained the old 20×16 hex records as compatibility/demo references; they no longer define the planet's dimensions or rendered geography.
- Consolidated QA results, changelogs, and release notes into `qa/`, `changelog/`, and `release/` respectively.

## Preserved
- Existing world, universe, system, character, and geography record identities.
- Existing persisted geography baselines; older records receive physical radius fallback from their saved world size without destructive migration.
- Existing Esc, M, C, and I interface shortcuts and the current gameplay menu structure.
- Existing account, character, universe, backup, log, credential, and player data.

## Notes and limitations
- Planetary radius is stored in kilometres. Babylon.js uses a compressed visual/gameplay scale so the planet and humanoid remain interactable without floating-point precision loss.
- The spherical surface is procedurally generated from the saved world seed at render time; the existing hex grid remains a legacy reference layer, not the source of global planetary geometry.
- Browser visual/interactive testing and multiplayer traversal around a complete globe have not been performed in this environment.
