# CORE 0.1.127 — Navigation hierarchy and share routing

## Navigation and terminology
- Renamed the Constellation-level screen to **Constellation View** throughout the player interface.
- Kept Universe as the parent data structure; no Universe View is presented.
- The Constellation selector and its toolbar are hidden while System View is active.
- The shared left navigation panel now switches between **Star Systems in This Constellation** and **Worlds in This System**.
- System View retains its orbital visualization and selected-World profile panel.
- Back returns to the parent Constellation and restores its Systems list.

## Entity identity
- Generated Worlds are named explicitly as `World <system ordinal>-<world slot>` rather than names that resemble additional Systems.
- World records retain their stable `wld_` IDs and explicit `system_id` / `constellation_id` parent references.

## Share-code API
- Added `api/universe-share.php`, a direct PHP entry point that dispatches to the existing authenticated `/api/universe/share` application route.
- Gameplay Copy Key and character-roster sharing now use the direct endpoint, avoiding dependence on extensionless Apache rewrite rules.
- Existing authorization and CSRF checks remain in the application router.

## Validation
- See the release QA report generated with this archive. Browser visual testing and live Apache verification remain deployment checks.
