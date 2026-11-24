# CORE 0.1.179 — Universe Navigation and Celestial Visibility

This update carries forward persistent diagnostics and addresses the UI/rendering issues previously reported.

## Universe navigation
- Universe View is now a true top-level overview of all constellations.
- Constellation markers have readable labels and system counts, with navigation links where route data supports them.
- Selecting a constellation enters its system map. The “Back to Universe View” control exists only in Constellation View.

## Planetary presentation
- Moon meshes are enlarged and separated into distinct sky lanes.
- Orbital tubes are substantially thicker and brighter.
- Starfield opacity is no longer allowed to fade to zero outside full night visibility.
- Diagnostics include star-layer state, moon scaling, and orbit sizing.

## Interface layout
- Planetary diagnostics is collapsed by default at the bottom-left, separate from the connection/status display at bottom-right.

## Verification boundary
PHP/JavaScript syntax and automated regression checks are performed for this package. Actual browser rendering of moons, orbits, Universe framing, and starfield behavior still requires verification in the installed environment.
