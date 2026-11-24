# CORE 0.1.145 — Avatar Visibility and Planetary HUD

This release corrects the avatar orientation basis matrix, improves character and terrain readability in dark conditions, and simplifies the planetary HUD.

## Changes
- Babylon matrix basis vectors are now supplied in column order, preserving local right/up/forward axes.
- Player material receives a modest emissive contribution for shadow visibility.
- Planetary ambient fill is raised so night terrain remains visible.
- Removed the test-world banner and map title strip from the 3D viewport.
- Map and current-location text now occupy one compact HUD component anchored to the viewport.

## QA
Automated and syntax checks are recorded in `qa/QA-RESULTS-0.1.145.txt`. Browser visual/runtime QA remains outstanding.
