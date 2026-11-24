# CORE 0.1.155 — Visible World Interaction Improvements

## Player-facing changes

- Spacebar initiates a jump. The jump arc follows the local planetary surface normal and returns under a gravity-driven trajectory.
- The `V` key opens a World Environment briefing showing physical and environmental properties currently present in the world payload. Missing or uncharacterized fields are shown as such rather than invented.
- Night-sky star rendering has been strengthened with explicit alpha blending, a dedicated rendering group, night-phase visibility, and reduced ambient fill after dark.

## Scope and limitations

- Jumping is currently a local visual action. Shared-world authoritative position remains on the planetary surface; remote jump-state replication, collision-aware jump physics, and multiplayer jump synchronization are not included.
- The environmental briefing displays existing generated data. This release does not claim to add detailed climate, atmosphere-composition, geology, hydrology, or biome generation where those data are not yet available.
- Star visibility has automated coverage but still requires browser/runtime verification under daylight and night conditions.

## Documentation

Updated `PROJECT_STATE.md`, `CORE-DESIGN-PRINCIPLES.md`, and `CORE-ROADMAP.md` to keep the continuity record aligned with this release.
