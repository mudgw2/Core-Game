# CORE 0.1.202 — Planetary Water, Relief, Spawn, and Developer Flight

## Planetary environment
- Added a distinct faceted spherical ocean mesh with depth-based vertex colors: turquoise shallows transition to deep navy water.
- Added subtle low-amplitude radial wave movement without reflection/refraction rendering or per-frame mesh reconstruction.
- Local terrain patch now places ocean vertices at sea level while land vertices retain generated elevation, so coastlines are represented by the same deterministic terrain field.
- Increased local landform variation across plains, hills, and mountain regions while retaining a positive local-patch datum above the coarse sphere.
- Extended the detailed local patch beyond the expected maximum third-person horizon to keep its square edge farther outside the visible skyline.

## Character spawn
- Spawn now prefers the primary population center (or spaceport) from the saved geography.
- The location is checked against the actual generated terrain field. If it is underwater, a deterministic outward search selects a dry-land location.
- If the generated world has no dry land, initialization reports that condition instead of placing the character in water.
- Existing geography, terrain definitions, authored modifications, and universe records are not rewritten.

## Developer fly mode
- Added F6 toggle for developer fly mode.
- WASD / arrow keys move relative to the camera; Space and Ctrl move vertically; Shift provides a 4x speed boost.
- Fly mode recenters the detailed terrain patch around the camera for environmental inspection and does not send normal character movement to multiplayer.
- F6 returns to the normal character camera and movement mode.

## Preserved
- Accepted celestial rendering behavior and celestial/environment fog isolation remain unchanged.
- No persisted universe, geography, terrain, or character data migration is included.
