# CORE 0.1.195 — Visible Planetary Terrain Relief

This update makes the existing procedural elevation field visible as actual planetary surface geometry. It increases the terrain sphere tessellation, applies a configurable 2.0x relief scale to sampled elevations (including authored features), and refreshes Babylon.js mesh bounds after displacement.

Existing 0.1.194 terrain JSON files remain valid and are not rewritten. They use the 2.0 relief default when the parameter is absent. Terrain colors and the independent climate layer are unchanged. Character spawn and surface movement use the same exaggerated elevation samples as the rendered mesh.

## Verification

Automated PHP test suite, PHP syntax checks, JavaScript syntax check, and ZIP integrity are recorded in the accompanying QA results. Browser visual verification requires installation and runtime inspection.
