# CORE Foundation 0.1.25 — Discovery Rewards & Character Inventory

## Gameplay changes
- Investigating a discovery now awards a persisted resource reward based on signal type: raw ore, rare ore, salvage parts, research data, energy cells, or intel fragments.
- Reward quantities are deterministic for a discovery ID and stored in the character inventory.
- Every award creates a ledger entry linked to the discovery and system.
- Investigation, reward, inventory totals, and ledger entry are committed in the same character transaction.
- Re-investigating an already completed discovery returns its saved reward but never grants it again or advances revision.
- The Scanning & Discoveries panel now shows inventory totals, recovery event count, and reward details for investigated signals.

## Reliability
- Existing revision checks, location checks, cross-universe validation, and error logging remain in place.
- QA uses temporary storage and does not alter saved user data.
- Package excludes live storage records and logs; preserve your existing `storage/data/` and `storage/logs/` during installation.

## Verification
Run Workbench → Operations & QA → **Run automated tests**. Expected result: **66 tests passed**.
