# CORE 0.1.193 — Persistent Fractal Planetary Terrain

## Included

- Added a persistent per-world terrain definition stored as an editable JSON record in `storage/data/world_terrain/<world-id>.json`.
- Terrain definitions are created atomically the first time a world requests terrain. Existing definitions are loaded as-is and are not regenerated over developer edits.
- Added deterministic spherical, domain-warped fractal terrain parameters for continental forms, regional relief, ridged mountains, and fine detail.
- The planetary renderer now consumes the saved terrain seed and parameters rather than independently inventing runtime noise offsets.
- Added authored modification records for mountains, volcanoes, meteor craters, and general elevation stamps. Multiple crater records can represent a meteor shower.
- Added a separate persistent climate JSON asset in `storage/data/world_climate/<world-id>.json`. Climate parameters remain independent from terrain elevation; the elevation lapse rate is recorded as a climate input.
- Preserved the existing geography record, legacy map reference, spherical coordinate system, and movement API. Terrain elevation is sampled by the existing movement/camera ground-clearance paths.
- Terrain mesh normals are recalculated from the displaced geometry for better relief shading.

## API

- `api/world-terrain.php` loads or creates the world's terrain and climate assets.
- Access is checked against the authenticated account's universe ownership/access before assets are returned.
- Existing valid JSON assets are not overwritten. Invalid or conflicting records return an error rather than silently replacing authored data.

## Not included yet

- No graphical terrain editor or elevation-map preview in this release.
- No climate-driven biome rendering, erosion, rivers, hydrology, or simulated geological events.
- Terrain modifications are currently authored directly in the JSON `modifications` array; a visual developer editor is a later milestone.

## Verification

- Assistant-run regression suite: **179 passed, 0 failed**.
- PHP syntax: all 108 project PHP files passed.
- JavaScript syntax: `player/planetary-environment.js` passed.
- A Node sampler smoke test loaded persistent terrain/climate payloads and sampled oceans, plains, hills, mountains, and an authored volcano modification.
- Browser visual verification is still required, especially terrain appearance, spawn placement, camera clearance, and walking across high-relief areas.
