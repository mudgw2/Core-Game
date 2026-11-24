# CORE 0.1.128 — Gameplay Sidebar and Test-World Selection

## Changes

- Reordered the gameplay sidebar so the active selection and its relevant entity list appear before secondary tools.
- Constellation View now presents Selected System, Star Systems in This Constellation, Multiplayer Presence, and route planning in that order.
- System View now presents Selected World, Worlds in This System, Multiplayer Presence, and route planning in that order.
- Moved the Selected World inspector into the shared sidebar and show it only in System View.
- Hide the redundant “View Selected System” action while already in System View; restore it when returning to Constellation View.
- Centralized test-world button state and click validation around the canonical current System record and selected World membership. The button and its click handler now use the same validation.
- Kept test-world entry restricted to a generated World in the character's current System.

## Verification

- PHP syntax checks: passed for changed PHP files.
- JavaScript syntax checks: passed for `player/player.js` and `player/system-view.js`.
- CORE automated test suite: **122 passed, 0 failed**.
- No browser visual test was performed. Install the patch and verify both views and Enter Test World in the browser.
