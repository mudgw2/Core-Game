# CORE 0.1.69 — Character management and gameplay boot repair

## Fixes
- Rebuilt the Workbench Characters & Travel tab around selectable character cards, with all saved characters discovered globally across universes and a clearer selected-character/travel management section.
- Character registry refresh now requests complete character records rather than a reduced data-inventory summary.
- Workbench deletion supports unassigned characters as well as universe-assigned characters. Deletion still requires exact-name confirmation and expected revision; assigned characters must supply their matching universe ID.
- Fixed the Play/gameplay boot failure. The old `player.js` was binding to creation-form controls that do not exist on the gameplay page, causing JavaScript to fail before initializing the map. Gameplay now consumes the already validated selected character and assigned universe directly and reports initialization/API errors in the status area.
- Kept gameplay character creation out of the game screen; Character Selection remains the way to switch characters.
- Added a regression test for safe deletion of unassigned characters and gameplay boot control compatibility.

## Verification
- `php -l` passed for changed PHP files.
- `node --check player/player.js` passed.
- `php tests/run.php`: 60 passed, 0 failed.
- The browser-only `/api/qa.php` endpoint could not run in this local command environment because the endpoint correctly reported `qa_disabled` for a non-permitted client. No live Apache/browser test is claimed.

## Installation
1. Back up the existing CORE files before applying the patch.
2. Extract this incremental ZIP over the installed CORE 0.1.68 root, preserving `storage/data/`, `storage/logs/`, and `storage/backups/`.
3. Do not extract into a new nested project folder; keep the paths from the ZIP relative to the CORE root.
4. Open the Workbench and refresh the character registry. Select a character card and confirm its details/actions populate.
5. Sign in to the player portal, open Character Selection, and click Play on a character with a valid assigned universe. Confirm the map appears and failures produce a readable error.

This patch changes source and tests only; it does not modify persisted user data.
