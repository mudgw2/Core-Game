# CORE 0.1.86 — Character Ability Generation

## Scope

This release completes the previously unfinished character ability-generation step. Ability scores are generated server-side using a data-driven Starfinder 1E-inspired point-buy model, with species adjustments applied after the purchased scores are calculated.

## Changes

- Added a 10-point ability point-buy configuration in `data/character-creation-rules.json`.
- Added the configured score range (7–16 before species adjustments) and point costs, including point refunds for scores below 10.
- Added Human +2 to one selected ability, Android +2 Dexterity / +2 Intelligence / −2 Charisma, and Ysoki +2 Dexterity / +2 Intelligence / −2 Strength.
- Added a live point counter, remaining-points display, final-score previews, and species-adjustment summary to Character Creation.
- Added the Human flexible ability selector; the client disables this field for species that do not use it.
- Enforced point-buy limits and species adjustments on the server. Client-side values are not trusted.
- Persisted both `base_attributes` and final adjusted `attributes`, along with the selected species adjustments and point-buy accounting. Existing gameplay reads continue to use `attributes`.
- Kept the editable character role/species/attribute catalog in `storage/data/content/character-options.json` untouched. New generation rules live in a separate application data file to avoid overwriting player-managed content.
- Advanced the application and Workbench cache version to `0.1.86`.

## Validation

- CLI QA: 78 passed, 0 failed.
- Browser QA endpoint: 138 passed, 0 failed (HTTP 200).
- PHP syntax: 86 project PHP files passed.
- JavaScript syntax: 5 project JavaScript files passed.

## Installation

1. Back up the current CORE source.
2. Extract this ZIP into the CORE project root, preserving the directory structure.
3. Do not overwrite `storage/data`, `storage/logs`, `storage/backups`, or `.git`; this patch does not contain files from those locations.
4. Confirm `config/version.php` returns `0.1.86`.
5. Test Character Creation with Human, Android, and Ysoki. Try an over-budget allocation and confirm it is rejected.
6. Run `php tests/run.php` and POST to `api/qa.php` from the trusted development/private network.

No live installation was modified.
