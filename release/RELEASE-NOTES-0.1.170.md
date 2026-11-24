# CORE 0.1.170 — Moon Diagnostic Markers and Orbital Paths

## Purpose

This is a diagnostic release. It does not claim to fix moon placement or prove final framebuffer visibility. It makes the existing moon positions unmistakable and visualizes the renderer's current apparent sky paths so their placement can be inspected in-game.

## Changes

- Moon 1 uses a bright cyan emissive diagnostic material; Moon 2 uses bright orange.
- Diagnostic moon materials remain opaque, self-lit, and bypass ordinary depth testing.
- A visible tube path is drawn for each moon using the same camera-relative sky frame and visual radius as the moon position calculation. These are **apparent presentation arcs**, not physical orbital trajectories.
- Diagnostic markers and paths are enabled by default.
- `CORE_ASTRONOMICAL_ENVIRONMENT.setMoonDebugVisuals(false)` disables the diagnostic appearance and paths while restoring the normal moon materials. This provides the switch needed for a future settings control.
- Runtime diagnostics report whether diagnostic materials and orbit paths are active.
- Moon, orbit mesh, and material resources are disposed when the world scene closes.

## Important limitation

This release is intended to answer where the renderer is placing the moons and whether their geometry/path meshes are visible. Automated tests cannot establish that the browser actually displays them. In-game confirmation is still required.
