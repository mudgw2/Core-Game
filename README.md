# CORE PHP Foundation 0.1.28 — Character Deletion

CORE is an early PHP/JSON browser-based sci-fi sandbox foundation. This release adds guarded permanent character deletion.

## Install
Extract the package over the existing application using your normal deployment process. **Preserve `storage/data/` and `storage/logs/`; never replace those directories with package contents.** The ZIP contains only `.gitkeep` placeholders for storage directories.

## Character deletion
In **Workbench → Characters & Travel**, select a character and use the **Danger zone: delete character** action. The UI asks for confirmation and requires the exact character name. Deletion permanently removes the selected character JSON record, including its progress, scans/discoveries, inventory/ledger, travel state, and ship loadout. Other characters and the universe remain untouched. The API requires the current character revision and matching universe and is restricted to development/private-network access.

## Important
This is still a development scaffold; authentication and account ownership are not implemented. Do not expose the application publicly. Character deletion is irreversible. Universe/global cleanup is intentionally not part of this release.

## QA
Open **Workbench → Operations & QA → Run automated tests**.
