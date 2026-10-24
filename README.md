# CORE PHP Foundation 0.1.24 — Discovery Investigation

## What is new in 0.1.24

- Scanned signals can now be investigated while the character is physically in the system where the signal was found.
- Investigation outcomes are persisted on the character's existing scan record with status and UTC timestamp.
- Repeated investigations return the saved result without incrementing the character revision.
- Stale revisions, unknown discoveries, and cross-universe character requests are rejected and logged.
- The Scanning & Discoveries tab shows investigation status, result text, and actions for unresolved signals.
- Existing global navigation, universe/character management, travel, scanning, and dashboard behavior remain available.

## Install / update

1. Back up the current CORE installation.
2. Extract this package over the application files.
3. **Preserve existing `storage/data/` and `storage/logs/` contents. Do not overwrite or delete those directories during an update.**
4. Load a test universe, select a character, scan its current system, and click **Investigate signal** for a result.
5. Confirm the result is displayed as investigated and persists after refreshing the character list.
6. Open **Operations & QA → Run automated tests**. Expected result: **PASS — 62 tests passed**.

## Current scope

This is a development-only prototype. Investigation outcomes are descriptive records, not resource rewards or inventory. Accounts, character ownership, progression/economy, and public access controls are not implemented. Do not expose development APIs publicly.
