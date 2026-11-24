# CORE 0.1.166 — Camera-View-Aligned Moon Presentation

## Problem

0.1.165 diagnostics showed moon meshes/materials were healthy, but their projected positions were outside the viewport. The camera was looking approximately 44 degrees below the local planetary tangent, while the prior visual moon arc remained horizon-anchored.

## Change

- Build the lunar presentation frame from the active camera's forward and screen-up axes.
- Map orbital phase into a bounded forward-facing arc rather than allowing moons to travel behind or far outside the active view.
- Preserve generated moon data, phase, period, inclination, radius, and simulation-clock progression.

## Design limitation

This is a camera-relative visual presentation model, not a physically accurate orbital path. A future astronomy update can replace it with a world-fixed orbital frame after the full camera/planet coordinate model is validated.

## QA

- Assistant PHP suite: 156 passed, 0 failed.
- JavaScript syntax: PASS.
- PHP syntax: PASS.
- Browser visual QA: not performed; user confirmation required.
