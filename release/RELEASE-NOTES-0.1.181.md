# CORE 0.1.181 — White Moon Visibility Proof

## Planetary moon presentation
- Replaced the cyan/orange diagnostic moon appearance with pure white, strongly emissive, unlit moon meshes.
- Moved moon meshes and their bright white orbit tubes to the final 3D rendering group. They remain opaque, depth-independent diagnostic objects.
- Added a temporary screen-space proof overlay above the planetary canvas. It projects each live moon position through the active camera and draws a large white marker with a dark outline and a `MOON 1` / `MOON 2` label.
- Added bright white screen-space orbital traces derived from the same per-frame presentation orbit points. These traces and markers cannot be hidden by terrain depth, Babylon scene meshes, or the starfield layer.
- Added diagnostics reporting whether the proof overlay exists and how many moons currently project into the visible camera viewport.
- The screen-space proof overlay is removed when the planetary environment is disposed.

## Scope and limitations
- This is deliberately a diagnostic visibility proof, not the final naturalistic moon appearance. The white circles and traces are screen-space overlays; the underlying 3D moon meshes and camera-relative, simulation-clock-driven presentation remain in place.
- The overlay only draws a moon when its projected position is within the active camera viewport and depth range. If the report says zero visible moons, the projection/placement issue remains measurable rather than being concealed.
- The previously reported unexplained dark sky obstruction is not claimed fixed by this release.

## Verification
- `php tests/run.php`: 170 passed, 0 failed.
- JavaScript syntax check: PASS.
- PHP syntax check: PASS.
- ZIP integrity: PASS.
- Browser visual verification: pending user confirmation. The acceptance target is two unmistakable white moon markers, labels, and white orbital traces in the planetary sky.
