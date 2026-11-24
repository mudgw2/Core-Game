# CORE 0.1.115 — Shared Universe Access and Hierarchical Procedural Seeds

## Shared universe access
- Added owner-generated, rotatable 32-character share codes for saved universes.
- Only a SHA-256 hash of the share code is persisted. The raw code is displayed once to the owner.
- Added authenticated character-selection workflows to share a home universe, visit a friend's universe, and return home.
- Visiting sets `active_universe_id` while preserving the character's permanent `universe_id`.
- The visitor's home system, location, and route progress are snapshotted and restored when leaving.
- Gameplay services and multiplayer grants resolve the active universe. Account checks allow a visitor to access only a universe their own character is actively visiting.
- Universe deletion is blocked while external characters are actively visiting, preventing dangling active-universe references.
- Shared universe and character records remain separate. A visitor's inventory and progression remain attached to their character.

## Hierarchical procedural identity
- Newly generated star systems receive their own deterministic `SYS-` seed.
- Legacy systems without a saved system seed derive one deterministically from the universe seed and stable system ID without rewriting saved universe records.
- Each generated world receives its own deterministic `WLD-` seed derived from its parent system seed and orbital slot.
- World profiles now derive from the individual world seed and expose both `seed` and `system_seed`.
- Each world in a system receives a distinct eight-character UWP. A deterministic collision adjustment prevents duplicate displayed UWP strings within the same system.
- Corrected the world-roll calculation so its range uses a bounded hash segment rather than converting the entire SHA-256 string to a PHP integer. This prevents the previous gas-giant/profile collapse caused by numeric overflow.
- Generation remains deterministic. Seeds define generated baselines; gameplay changes remain in persistent state and are not overwritten by regeneration.

## Data safety
- No migration or rewrite of existing universe baseline records is performed.
- Existing character home-universe associations are not changed.
- The first visit creates only temporary active-universe and home-location context fields on that character record.
- No account, credential, player, universe, log, or backup data is included in this patch.

## Verification
- CLI PHP test suite: 113 passed, 0 failed.
- Multiplayer TypeScript build and Node tests: 20 passed, 0 failed.
- PHP syntax checks: passed for changed PHP files.
- JavaScript syntax checks: passed for changed browser scripts.
- Protected browser QA endpoint could not be executed from this environment because its trusted-network/development access guard rejected the local CLI request. It must be run from the user's permitted browser/LAN context.
- Two-account browser validation remains required: generate a share code on account A, visit with a character on account B, verify both players inhabit the same universe, then leave and confirm account B returns to its home universe.
