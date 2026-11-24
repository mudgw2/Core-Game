# CORE 0.1.146 — Character Turning & Map Positioning

## Changes

- A/D and left/right arrows turn the character left/right in place and while moving.
- W/S and up/down move forward/backward relative to the character's facing direction.
- Character heading remains tangent to the planetary surface as the character moves.
- Initial facing direction follows the camera's projected tangent-forward direction.
- The transparent map HUD is anchored to the upper-right, below the top header.
- In-game controls documentation now describes turning and movement separately.

## Validation

Automated test and syntax-check results are recorded in `qa/QA-RESULTS-0.1.146.txt`. Browser visual/interaction QA has not been performed by the release builder.

## Data safety

The patch contains only application code, tests, and release notes. It does not replace persistent player or universe data.
