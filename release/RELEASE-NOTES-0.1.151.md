# CORE 0.1.151 — Server Clock and Spherical Movement

## Changes
- Added a persistent server-authoritative simulation clock scoped to a universe and world.
- World entry bootstraps from stored simulation time; clients synchronize every 15 seconds.
- `[` and `]` submit server-side one-hour adjustments. Clock updates are serialized with a dedicated lock and written by atomic file replacement.
- Added authenticated, CSRF-protected `/api/world/clock` endpoint.
- Initial planetary heading now uses the explicit positive-longitude tangent. Movement uses a cloned input vector so forward/reverse input cannot mutate the retained heading.

## Storage
Clock records are created under `storage/data/simulation_clocks/`. Existing player, character, and universe records are not rewritten.

## Installation
Back up the installation first. Extract this patch at the CORE project root, preserving relative paths. Do not overwrite or replace the `storage/` directory. No database migration is required.
