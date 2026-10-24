# CORE Foundation 0.1.27 — Ship Fitting

## Gameplay
- Adds a persisted ship loadout with propulsion, scanner, and cargo slots.
- Install a propulsion array (1 ship component + 1 refined alloy), survey scanner (1 ship component + 1 tech insight), or expanded cargo rack (1 ship component + 2 refined alloy).
- Each slot accepts one module; occupied slots cannot be overwritten in this release.
- Installation consumes materials and records each input in the character inventory ledger in the same transaction as the fitted module.
- Successful installation increments character revision once. Insufficient resources, occupied slots, stale revisions, unknown modules, and cross-universe requests are rejected without state changes.
- Workbench shows installed module slots and status feedback; API failures are logged.

## Reliability
- API is restricted to development mode and local/private-network clients.
- QA uses temporary storage. Preserve existing `storage/data/` and `storage/logs/` contents when installing.

## Verification
Run Workbench → Operations & QA → **Run automated tests**. Expected result: **74 tests passed**.
