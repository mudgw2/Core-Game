# CORE 0.1.129

## Gameplay navigation and test-world entry

- Reordered the gameplay sidebar so the active selected entity appears first, the relevant entity list second, Multiplayer Presence third, and route/navigation support afterward.
- Moved the Universe Data summary below the primary navigation controls.
- Kept **View Selected System** hidden while System View is active.
- Synchronized the active System identity when System View opens.
- Reworked test-world eligibility around the active System View identity and the character's canonical current System.
- Validates that the selected World has a stable ID and belongs to the active/current System before enabling entry.
- Uses the validated System and World records when building the test-world URL.
- Preserves the server-side ownership, lifecycle, current-System, and generated-world checks in `player/world.php`.

## Verification

- PHP project test suite: 122 passed, 0 failed.
- PHP syntax: passed.
- JavaScript syntax (`node --check`): passed.
- Browser interaction/visual QA: not performed.
