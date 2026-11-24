# CORE 0.1.147 — Planetary Time & World Diagnostics

## Included
- Persistent planetary local-time readout with a Sun (day), twilight, or Moon (night) indicator.
- Clock and day count derive from the existing accelerated planetary rotation simulation.
- Developer-only World Information entry in the Esc menu. It is emitted only for development deployments accessed from a trusted local/private network address.
- Read-only inspection of the exact world payload, available geography, selected physical properties, system context, and runtime environment status.
- Natural satellite presentation now requires an explicit moon/satellite record in world data; the time-of-day Moon icon is only an illumination indicator.

## Validation
- PHP syntax: `player/world.php`, `config/version.php`.
- JavaScript syntax: `player/world.js`, `player/astronomical-environment.js`.
- CLI regression suite: 139 passed, 0 failed.
- Browser visual/runtime QA was not performed in this environment.
