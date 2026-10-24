# CORE PHP Foundation 0.1.26 — Crafting Bench

CORE is a browser-based, seed-driven sci-fi sandbox prototype built with PHP, JSON persistence, and a browser Workbench.

## What changed in 0.1.26

- Added a revision-checked character crafting service and development-only API.
- Refine 3 raw ore into 1 refined alloy.
- Assemble 2 salvage parts into 1 ship component.
- Analyze 2 research data into 1 tech insight.
- Supports 1–10 batches per operation; input consumption and output production are committed together.
- Craft input/output transactions are recorded in the character inventory ledger.
- Workbench crafting controls refresh character inventory and revision after successful crafting.
- Failed crafting (insufficient materials, invalid quantity, stale revision, or invalid recipe) does not change inventory.

## Install / update

1. Back up the current CORE installation.
2. Extract this package over the application files.
3. **Preserve existing `storage/data/` and `storage/logs/` contents. Do not overwrite or delete those directories during an update.**
4. Open Workbench → Operations & QA → **Run automated tests**. Expected result: **PASS — 70 tests passed**.
5. Open Scanning & Discoveries. Investigate discoveries to collect resources, then use the Crafting bench when you have enough inputs.

## Safety / scope

This remains a development-only prototype. The crafting API is restricted to development mode on localhost/private networks. Before public exposure, change the application environment and disable browser QA according to `config/app.php`. Accounts, ownership, trade, and multiplayer synchronization are not yet implemented.
