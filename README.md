# CORE PHP Foundation 0.1.22 — Travel Control Reliability

## What's new in 0.1.22

- Characters can scan their current system from the Workbench.
- Scans generate 1–3 deterministic signal discoveries from the universe seed fingerprint and system ID, so the same universe/system produces stable findings.
- Scan records are persisted on the character under `scans.systems`, including timestamp, system identity, and discovery details.
- Scanning a system for the first time increments the character revision. Repeating a scan returns the saved results with `already_scanned` and does not increment revision.
- The scan API checks character ownership by universe and expected character revision; invalid/stale requests are rejected and logged.
- The Workbench shows current-system scan status, accessible discovery descriptions, and the character’s scan history; it refreshes the character revision after scanning.
- Added isolated browser QA coverage for persistence, repeat-scan idempotency, stale revisions, cross-universe rejection, and UI wiring.

## Install / update

1. Back up the current CORE installation.
2. Extract this package over the application files.
3. **Preserve existing `storage/data/` and `storage/logs/` contents. Do not overwrite or delete those directories during an update.**
4. Open the Workbench, load a test universe, select or create a character, and use **Scan current system**.
5. Run **Workbench → Run automated tests**. Expected result: **PASS — 53 tests passed**.

## Current scope

This remains a development-only prototype. Character scans are character-specific; the scan result catalogue is deterministic but currently informational. Resource extraction, combat, accounts, ownership, and public access controls are not implemented. Do not expose development APIs publicly.


## What's new in 0.1.22
- Fixed Workbench travel-control feedback: **Travel next jump** remains clickable whenever a character is selected, so stale/missing route state produces an explicit status message instead of a silently disabled control.
- Active routes keep the next-jump control enabled.
- Travel request exceptions are shown in the travel status area and written to the Activity Log.
- Added a regression test for next-jump event wiring, enabled state, and visible error feedback.
- Existing travel API revision checks and route validation remain unchanged.
