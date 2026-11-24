# CORE 0.1.126 — Constellation Navigation and System View

## Navigation correction

- Gameplay now opens in **Universe View** at the character's current Constellation; Play no longer automatically opens the character's current System.
- Universe View displays only the Star Systems belonging to the selected Constellation.
- Added a Constellation selector and a selectable Star System list, synchronized with the 3D map.
- Selecting a System opens **System View**, where the Worlds belonging to that System are listed and can be selected alongside the orbital visualization.
- **Back to Universe View** resolves the selected System's parent Constellation, restores that Constellation's Systems, and redraws the map instead of merely toggling panel visibility.
- Travel destinations remain universe-wide; changing the Constellation selector changes the view, not the character's saved location.

## QA and multiplayer

- Corrected the QA hierarchy-seed assertion to verify the actual variable-based System seed assignment and World seed assignment.
- Gameplay now references the runtime World profile's UWP through its selected World record.
- Multiplayer grant acquisition uses the canonical `/api/multiplayer/token` route first, with the direct PHP endpoint retained as a fallback for Apache installations without API rewrites.
- Added a regression test for initial Constellation context, Constellation-scoped System rendering, System View entry, and parent-Con­stellation restoration.

## Data and compatibility

- No persistent generated universe data is rewritten by this navigation update.
- Existing account, character, universe, and multiplayer records are unchanged by the new view state.
- Shared-universe visitors are validated against `active_universe_id` while retaining their permanent `universe_id` as the home association.
- After a completed travel jump, Universe View follows the character into the destination System's parent Constellation.
- Returning from System View restores the previous Universe View camera position and zoom as well as the parent Constellation.
