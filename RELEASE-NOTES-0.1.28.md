# CORE PHP Foundation 0.1.28 — Character Deletion

## Added
- Permanently delete a selected character and all character-specific data stored in its single JSON record.
- Require a destructive confirmation dialog, exact character-name re-entry, the owning universe ID, and the current character revision.
- Perform deletion while holding the character record lock to avoid racing concurrent character writes.
- Restrict the deletion API to development mode and local/private-network requests, matching the existing character API safeguards.
- Log successful deletion, validation rejections, revision conflicts, access denials, and unexpected failures.
- Preserve the universe, other character records, and all storage/log files unrelated to the selected character.

## Safety notes
- Character deletion is permanent and cannot be undone by CORE. Back up data before using destructive operations.
- This release does not implement universe deletion or global data cleanup. A future whole-data cleanup tool should be a separate, explicitly scoped operation with a dry run, impact summary, confirmation phrase, and safeguards that preserve logs unless the operator explicitly chooses otherwise.

## Verification
- Browser QA suite adds coverage for successful deletion, isolation from sibling characters/universe data, exact-name and revision guards, cross-universe rejection, missing-record handling, and Workbench wiring.
