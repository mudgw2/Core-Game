# CORE Foundation 0.1.23 — Global Workbench Navigation

## What changed

- Added a global five-area menu: Overview, Universe, Characters & Travel, Scanning & Discoveries, and Operations & QA.
- Split the Workbench into focused tab panels without reloading the page or discarding the currently selected universe/character state.
- Added accessible tab semantics, selected-state indicators, visible focus styling, and keyboard navigation using Left/Right or Up/Down arrows, Home, and End.
- Added a compact Overview dashboard showing the currently loaded universe seed context, selected character, current system, and travel progress.
- Kept server status in Overview; universe creation and state events in Universe; character movement and route execution in Characters & Travel; scans in Scanning & Discoveries; response output, activity log, and QA in Operations & QA.
- Added regression tests for tab/panel relationships, keyboard behavior, and overview dashboard refresh wiring.

## Update instructions

1. Back up the current CORE installation.
2. Extract this release over the application files.
3. Preserve existing `storage/data/` and `storage/logs/` contents. Do not overwrite or delete those directories.
4. Navigate through each global menu tab, create/load a test universe, select a character, and confirm the overview reflects the selected context.
5. Run Workbench → Operations & QA → Run automated tests. Expected result: **PASS — 57 tests passed**.

This remains a development-only prototype. Do not expose development APIs publicly before authentication and access controls are implemented.
