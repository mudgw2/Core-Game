# CORE 0.1.141 — Planetary Gameplay Corrections

This focused patch corrects the planetary test-world issues reported after 0.1.140.

## Included
- Map-center starting location: 0° latitude / 0° longitude.
- Camera terrain clearance check during the gameplay render loop.
- Astronomical sky visibility improvements, including a deterministic visual moon placeholder.
- Floating planetary map HUD rather than a centered modal dialog.
- Regression coverage for M (map), C (character information), and I (inventory).

## Notes
The moon is currently a presentation-only placeholder derived from the world seed. It is not a generated, persistent celestial-body record. Actual moon generation remains a separate future system.

The camera clamp and UI behavior have automated source regression coverage, but browser visual/runtime QA remains outstanding.

## Data safety
This patch does not include or replace storage, player data, accounts, universes, characters, backups, logs, secrets, or generated world data.
