# CORE PHP Foundation 0.1.38 — Read-only Lock Compatibility

This release fixes the universe deletion workbench flow with explicit selection, scalable search, visible confirmation feedback, progress messages, and actionable error reporting.

## Universe deletion workflow

1. Refresh the saved-data inventory.
2. In Data Management → Permanent universe removal, search by universe name or ID and select the intended universe.
3. Click **Preview selected universe deletion** and inspect the cleanup impact preview.
4. Select and manually copy the exact generated phrase `DELETE UNIVERSE <actual-universe-id>`.
5. Type the phrase exactly. A high-contrast live status reports mismatch or match; the destructive button remains disabled until it matches.
6. Click **Back up and delete universe**. Progress is displayed, and failures are shown in the panel and Activity Log.

The generic cleanup record selector also supports search by name or ID, with a matching-record count. This makes selection practical as inventories grow. Search is client-side over the current read-only inventory; pagination/server-side search can be added when data volumes warrant it.

Universe deletion remains guarded by a fresh impact preview, server-side confirmation, revision and dependent-character checks, pre-deletion verified backup, deletion verification, and rollback attempt on failure. A failed request invalidates the preview and requires a new preview before retrying.

## Validation (0.1.37 baseline)

- Browser QA: 103 tests expected.
- PHP CLI tests: 15 tests expected.
- The package does not include live storage data or operational log contents. Preserve the server's existing `storage/data/`, `storage/logs/`, and `storage/backups/` when installing.


## Focused logs (0.1.36+)

All entries continue to be written to `storage/logs/core.log`. Matching entries are also routed to focused JSON-lines logs in the same directory: `api.log`, `storage.log`, `universe-deletion.log`, `backups.log`, `security.log`, `php-errors.log`, and `errors.log`. Keep the complete `storage/logs/` directory when upgrading.

Universe deletion preflights both collection directories and every existing target record lock before creating a backup. A restrictive existing lock file fails early with its exact path. Failed deletion and rollback responses direct operators to `storage/logs/universe-deletion.log` and `storage/logs/backups.log`; rollback failures are written to both focused logs and the central log.


## 0.1.37 usability fixes

The unreliable Copy phrase button has been removed. After generating a fresh preview, select the full required phrase in the read-only field and copy it with the normal keyboard shortcut. Deletion failures now direct the operator to `storage/logs/universe-deletion.log` and `storage/logs/backups.log`, as well as the Workbench Activity Log, rather than incorrectly pointing to `core.log`.


## 0.1.38 storage lock fix

Existing JSON lock files no longer need write permission. CORE opens a readable read-only lock file and obtains the same exclusive OS file lock with `flock()`. It never unlinks/recreates an existing lock file to work around permissions, avoiding races where concurrent requests could lock different inodes. Destructive-operation preflight now checks whether each existing lock can actually be opened and locked, instead of rejecting every lock that is not writable.

If a lock file is not readable at all, CORE fails safely with its path and does not delete records; the application cannot safely replace an inaccessible lock file without risking concurrent access.


## 0.1.38 validation

- Browser QA: 103 passed, 0 failed.
- PHP CLI tests: 15 passed, 0 failed.
- All PHP files passed `php -l`.
- Inline JavaScript passed `node --check`.
- The regression test verifies that CORE can use a read-only lock file without changing its permissions.
