# CORE 0.1.167 — Planetary-Horizon Moon Presentation

- Moon sky azimuth follows the camera heading projected onto the local planetary tangent plane.
- Moon elevation is measured from the outward local surface normal and constrained above the horizon.
- Added `aboveLocalHorizon` to moon renderer diagnostics.
- Preserves generated moon data and simulation-clock-driven phase progression.
- This remains a visual presentation arc, not a physically accurate orbital solution.
