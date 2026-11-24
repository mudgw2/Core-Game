# CORE 0.1.180 — Hierarchy Labels and Planetary Celestial Visibility

## Universe and Constellation views
- Corrected the vertical UV orientation used by Babylon.js billboard labels so constellation and system names render upright.
- Added readable, always-visible star-system labels and world counts in Constellation View.
- Increased constellation/system map spacing and marker scale to reduce the empty-canvas effect and make hierarchy entities easier to identify.
- Repositioned labels around their markers to reduce label collisions.
- Removed the self-referential “Back to Universe View” control from Universe View navigation.
- The Constellation View grid remains absent.

## System View
- Added persistent upright labels above every planet, including its name and UWP.
- Added persistent labels above moons identifying their names and parent worlds.
- Increased System View moon marker size and emissive brightness. Existing hover identification and selection remain.

## Planetary view
- Brought the visible moon presentation arc closer to the camera so moons occupy a larger, more legible portion of the sky.
- Increased moon apparent size and emissive debug contrast.
- Increased orbital-path tube thickness and emissive brightness; separated the apparent sky lanes.
- Preserved generated moon records, their deterministic IDs/parameters, and simulation-clock-driven phase progression. This remains a camera-relative visual presentation arc, not a physical N-body orbit.
- Retained the screen-space star layer and diagnostics. The unexplained dark sky obstruction is not claimed fixed; the next runtime report should identify the responsible mesh if it persists.

## Diagnostics and layout
- Retained persistent browser/server diagnostics. The planetary diagnostics panel remains collapsed and positioned bottom-left, separate from lower-right connection information.

## Verification status
- Automated regression tests and syntax checks are recorded in `qa/QA-RESULTS-0.1.180.txt`.
- Browser visual verification is pending. In particular, confirm upright labels, label spacing, visible moons/orbits, and whether the dark upper-right sky obstruction remains.
