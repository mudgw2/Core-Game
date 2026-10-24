# CORE Foundation 0.1.26 — Crafting Bench

## Gameplay
- Adds three recipes: raw ore → refined alloy, salvage parts → ship components, and research data → tech insights.
- Batch size is limited to 1–10 crafts per request.
- Crafting consumes the required materials and creates output within a single character-record transaction.
- Every input consumption and output production is recorded in the inventory ledger.
- Character revision increments once after a successful crafting transaction.
- Insufficient resources, invalid recipes, invalid batch sizes, stale revisions, and cross-universe character requests are rejected without inventory changes.
- Workbench controls show recipe, batch quantity, result status, and refreshed inventory.

## Reliability
- Crafting is available only through the existing development/private-network API policy.
- Server-side failures and rejected operations are logged.
- QA operations use temporary storage and do not modify saved user data.
- Preserve existing `storage/data/` and `storage/logs/` contents when installing.

## Verification
Run Workbench → Operations & QA → **Run automated tests**. Expected result: **70 tests passed**.
