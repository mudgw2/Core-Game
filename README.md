# CORE PHP Foundation 0.1.23 — Global Workbench Navigation

## What's new in 0.1.23

- Added global tabs for Overview, Universe, Characters & Travel, Scanning & Discoveries, and Operations & QA.
- Added accessible keyboard navigation and clear active-tab state.
- Added a compact Overview dashboard that reflects the loaded universe, selected character, current system, and route progress.
- Existing character movement, multi-hop travel, system scanning, persistence, revision checks, and logging remain in place.

## Install / update

1. Back up the current CORE installation.
2. Extract this package over the application files.
3. **Preserve existing `storage/data/` and `storage/logs/` contents. Do not overwrite or delete those directories during an update.**
4. Navigate through the five global menu tabs. Create/load a test universe, select a character, and verify the Overview updates.
5. Open **Operations & QA → Run automated tests**. Expected result: **PASS — 57 tests passed**.

## Current scope

This remains a development-only prototype. Accounts, character ownership, gameplay progression, and public access controls are not implemented. Do not expose development APIs publicly.
