# CORE 0.1.154 — Deterministic World Physics & Multi-Moon Orbits

## Summary
This incremental release advances world data and astronomical presentation without changing existing persisted universe records.

## Changes
- Generated world profiles now include deterministic estimated radius, mass, surface gravity, rotation period, and axial tilt.
- Estimates are explicitly tagged `seeded_estimate_v1` and `estimated: true`; they should not be treated as measured astrophysical values.
- Generated worlds now include a deterministic list of zero to three moons (gas giants receive at least one), with stable IDs and approximate diameter/orbital radius/period/inclination/phase.
- The astronomical renderer creates every supplied moon and advances its orbit from the server-authoritative simulation elapsed time.
- Updated project state, design principles, and roadmap documentation.

## Compatibility
- Existing universe records are not rewritten; world profiles remain deterministically derived at runtime.
- Existing worlds with no moon array remain supported.
- Orbital distances are visually compressed; this is a presentation model, not an N-body simulation.
- No storage migration is required. No player data or storage files are included.

## Not included
- Spacebar jumping (next planned movement increment).
- Full geography/climate/geology simulation.
- Full gravitational or N-body orbital mechanics.
