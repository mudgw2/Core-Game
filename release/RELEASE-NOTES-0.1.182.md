# CORE 0.1.182 — Planet-Fixed 3D Moon Orbits

## Planetary moon coordinate correction
- Removed the screen-space SVG moon proof overlay. Moon markers are now the actual Babylon.js sphere meshes and orbital tubes.
- Replaced the camera-relative sky frame with a planet-fixed orbital coordinate frame defined by the planet's fixed world axes.
- Moon positions are anchored to the planet's world-space origin (`0,0,0`) and use generated orbital-radius and inclination data.
- Moon positions no longer depend on the player's position, live camera position, or live camera heading. Rotating the camera therefore changes their screen projection naturally without moving the moons in world space.
- Orbital paths are complete 3D tube meshes around the parent planet and use the same orbital basis and radius as their moons.
- Orbital phase continues to advance from the authoritative simulation clock and each moon's orbital period.
- The initial orbital phase places the diagnostic moons in a separated forward-facing portion of the fixed orbital plane; this is a one-time initialization, not camera tracking.
- Bright white emissive development materials, visible orbit tubes, final rendering group, and depth-independent diagnostic rendering are retained so the actual 3D objects remain conspicuous during development.
- Diagnostics now explicitly report `worldAnchored: true`, `cameraRelative: false`, orbital radius, orbit center, orbit-plane normal, and actual camera projection.

## Scope and limitations
- The planet-fixed coordinate frame uses the planet's world axes only. Neither initial nor live camera orientation determines orbital positions.
- Development rendering bypasses scene depth testing so the white diagnostic moons/orbits remain visible even when another scene surface would normally occlude them. This is intentional for diagnosis; it may show a moon through the planet when it is on the far side. Naturalistic planet/terrain occlusion is a later step after 3D motion and camera behavior are confirmed.
- No Universe, Constellation, or System View UI changes are included in this focused moon correction.

## Verification
- Automated regression suite: see `qa/QA-RESULTS-0.1.182.txt`.
- JavaScript syntax: verified with Node.
- PHP syntax: verified for changed PHP files.
- ZIP integrity: verified after packaging.
- Browser visual verification: pending user confirmation. Acceptance requires the moons to move naturally across the view as the camera turns, remain fixed in planet-space coordinates, and advance along visible 3D orbital paths with simulation time.
