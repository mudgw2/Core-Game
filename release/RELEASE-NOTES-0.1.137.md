# CORE 0.1.137 — Planetary Geography to Babylon.js Foundation

## Added
- The planetary test world now requests the existing authenticated, persistent geography API before creating its Babylon.js scene.
- Added `player/planetary-environment.js` as a dedicated geography-to-Babylon.js integration component; `world.js` delegates geography loading, terrain creation, and surface-height sampling to it.
- The 3D environment builds a continuous subdivided terrain surface from the saved geography hex records.
- Terrain classification and elevation indices influence vertex color and terrain height for water, plains, hills, and mountains.
- Terrain elevation is interpolated from nearby geographic hex centers, and the local character follows the generated surface while moving.
- The initial character spawn uses the primary population-center hex when available, then a spaceport hex, then the first valid map hex. This is a test-world spawn fallback, not a persisted character location assignment.
- The HUD now includes the selected spawn hex ID alongside local coordinates.

## Preserved
- Existing character model loading, animation selection, camera controls, local movement, and multiplayer session flow.
- Existing geography generator, saved JSON records, API access controls, and player data.
- No changes to persistent character records or saved geography.

## Scope and limitations
- This is the first data-to-3D integration foundation, not a finished planetary renderer.
- Terrain is a local continuous patch sampled from the saved planetary hex map. Terrain colors currently distinguish the four existing classifications; geological detail, climate, vegetation, structures, and persistent character hex location remain future work.
- The initial spawn selection does not modify character location data.
