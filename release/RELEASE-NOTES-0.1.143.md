# CORE 0.1.143 — Planetary Movement & Readability

## Scope

This release addresses the movement and presentation problems reported after 0.1.142.

## Changes

- Character facing now uses a complete surface-relative orientation basis. Movement heading is no longer discarded by a per-frame surface alignment assignment.
- Heading is transported along the spherical surface as the character moves.
- Local coordinate conversion now shares the map's center convention: +X is 0° latitude / 0° longitude, +Z is east, and +Y is north.
- Night-time ambient fill is raised to improve terrain and avatar visibility while retaining stellar directional illumination and the day/night cycle.
- The map terrain legend is removed. The Current Location readout remains immediately below the map.

## Data safety

No persistent universe, world, character, account, or generated geography records are migrated or replaced.

## QA boundary

CLI tests and syntax checks are reported separately. Browser visual and interaction QA has not been performed by this build process.
