# CORE 0.1.177 — Constellation Grid Removal and Moon Views

- Constellation View no longer creates or displays the wireframe ground grid.
- System View now renders generated moons around their parent planets, with compressed orbital radii for legibility and motion derived from each moon's orbital period and phase.
- Planetary moon diagnostic orbit tubes now initialize with the same 65-point path topology used during per-frame updates, avoiding Babylon.js Path3D topology mismatch errors.

Automated regression tests verify the source contracts. Browser visual/runtime verification has not been performed; confirm both moon displays in the installed application.
