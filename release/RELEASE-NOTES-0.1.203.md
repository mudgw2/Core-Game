# CORE 0.1.203 — Fly Controls, Landing Continuity & Authoritative Spawn

## Fixes
- Space remains the normal character jump control; it is not captured for developer flight.
- Shift remains the normal character run control and no longer changes developer flight speed.
- Developer fly mode uses Page Up / Page Down for vertical movement.
- Exiting fly mode lands at the current fly-camera location, selecting a nearby dry-land surface position instead of restoring the pre-flight avatar coordinates.
- Multiplayer authoritative interpolation is suspended while developer fly mode is active.
- The validated dry-land spawn is sent to the authenticated multiplayer room as the initial player transform, preventing the server's default `(0, 0)` transform from moving the avatar back to an unrelated location.
- Flight landing position is synchronized back to the multiplayer room.

## Preserved
- Normal WASD movement, Shift-to-run, and Space-to-jump behavior.
- Existing terrain, water, universe, geography, and authored terrain data.
- Existing celestial rendering and fog isolation.
