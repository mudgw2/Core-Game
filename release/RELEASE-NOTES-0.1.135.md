# CORE 0.1.135 — First-Draft Planetary Geography

## Scope
Adds a deterministic, UWP-informed planetary geography baseline and an authenticated JSON export endpoint.

## Geography
- Generates a 20 x 16 map (320 hexes) using 50 km hexes.
- Uses the stable world seed, world ID, and UWP as deterministic generation inputs.
- Hydrographics determines the exact proportion of water hexes.
- Neighbor-smoothed deterministic fields create regional patterns for water, mountains, hills, and plains.
- Population rating determines the number of population-center markers.
- Starport classification A–E creates a stable spaceport marker; X does not.
- Hex records expose stable IDs, coordinates, terrain, features, water state, elevation index, and a per-hex seed fingerprint.
- Generator/schema versions and a generation fingerprint are included in the JSON.

## API
GET `/api/world/geography?universe_id=<id>&system_id=<id>&world_id=<id>`

The endpoint requires an authenticated account with access to the requested universe. It validates that the world belongs to the specified system, generates the geography, and persists the first generated baseline in `storage/data/world_geography/` through CORE's atomic JSON storage. Later requests return the persisted baseline rather than silently replacing it when generation code changes.

An explicit Apache-compatible entry point is included at `api/world-geography.php`.

## Deliberate exclusions
Temperature, climate, weather, atmosphere effects, vegetation, civilization, Tech Level mechanics, settlements/buildings, 3D terrain meshing, terrain streaming, and player movement are not part of this draft. Population centers and spaceports are geographic markers only.

## Validation
PHP syntax checks and the CLI suite were run against the supplied source archive. Results: 128 passed, 0 failed.

## Installation
Extract the patch into the CORE project root, preserving paths. Do not overwrite `storage/`, player records, accounts, credentials, backups, logs, or `.git/`. The new `world_geography` collection will be created automatically when the geography endpoint is first called.
