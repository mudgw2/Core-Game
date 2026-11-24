# CORE 0.1.125 — Hierarchy and Gameplay Selection Corrections

Install by extracting the included files into the CORE project root, preserving paths. This is a source patch only; it does not include player records, generated universe data, accounts, credentials, backups, logs, `node_modules`, or build output.

## Universe hierarchy and runtime payload

- Completes the player-facing runtime payload as Universe → Constellations → Star Systems → Worlds.
- Runtime-generated World profiles are attached to their nested parent System records as well as the flat System lookup index used by existing navigation and gameplay services.
- World records consistently carry their parent System and Constellation identities and seeds.
- Existing universes with obsolete or incomplete hierarchy records are regenerated from their stored seed/settings on load or shared-universe entry. The stable Universe ID, sharing metadata, and persistent universe state are retained.
- Characters whose active or saved home location belongs to a rebuilt Universe are moved to its deterministic first System; active visitor locations are not overwritten when only their home Universe is rebuilt. Stale travel routes are cleared.
- When a System seed needs to be derived, its parent Constellation seed is used rather than deriving directly from the Universe seed.

## Gameplay interface and selection

- Corrects the gameplay terminology: Selected System, Worlds in This System, and View selected system.
- Displays Current Constellation and Current System as separate values, and reports Constellation and System counts independently.
- Clarifies that the universe overview map and travel destinations represent Star Systems.
- Restores working World selection from the list: selecting a World updates the selected-world state, profile inspector, selection highlight, and world-entry control.
- Preserves a list-selected World when opening the 3D System view; selecting a planet in the 3D view also updates the list selection.
- The initial System is now taken from the selected character's authoritative saved location. A stale or manually supplied `system_id` URL parameter can no longer override the character location.

## Location and shared-universe corrections

- Fixes the shared-universe visit overwrite that discarded the destination Constellation ID from the character's location record.
- Shared visit location now retains Constellation ID, System ID, and active Universe ID together; home location snapshots and return behavior remain intact.
- Removes a redundant location assignment during route advancement while retaining the authoritative Constellation and System location.
- Includes the character's Constellation ID in multiplayer authorization claims and grant identity metadata.

## Validation

- PHP QA suite: 119 passed, 0 failed.
- PHP syntax checks: passed for all project PHP files.
- JavaScript syntax checks: passed for player and asset scripts.
- Multiplayer TypeScript build and tests: 20 passed, 0 failed.
- Browser visual and live-server testing were not performed. The user's installed build should be tested for rendered labels, World list interaction, 3D selection synchronization, and the multiplayer authorization request.
