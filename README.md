# CORE PHP Foundation 0.1.39 — Atomic Universe Deletion & Recovery

This release hardens destructive universe cleanup with a staged, multi-record filesystem transaction, coordinated character creation, and a checksum-verified backup as a second recovery layer.

## Universe deletion workflow

1. Refresh the saved-data inventory.
2. In Data Management → Permanent universe removal, search by universe name or ID and select the intended universe.
3. Click **Preview selected universe deletion** and inspect the cleanup impact preview.
4. Select and manually copy the exact generated phrase `DELETE UNIVERSE <actual-universe-id>`.
5. Type the phrase exactly. A high-contrast live status reports mismatch or match; the destructive button remains disabled until it matches.
6. Click **Back up and delete universe**. Progress is displayed, and failures are shown in the panel and Activity Log.

## 0.1.39 deletion transaction hardening

- Universe and associated character JSON records are locked and validated as one set, with a stable lock order: universe first, then characters sorted by ID.
- Character creation now uses the same universe lock, preventing a new dependent character from appearing between the final dependency check and deletion.
- After a checksum-verified backup exists, deletion stages live JSON files by same-filesystem rename into `storage/data/.transactions/`. The application verifies the complete staged set before considering deletion committed.
- If staging fails partway, CORE attempts to rename all moved records back and verifies the restored live records before reporting the failure.
- If filesystem rollback cannot be verified, remaining staged records are preserved as recovery evidence and the critical event includes the transaction ID/path in the focused deletion log. CORE then attempts the checksum-verified backup as a second recovery layer.
- Existing `.json.lock` files are deliberately retained. Removing or recreating lock files could split concurrent locks across different inodes.
- A final guard rejection before any record is moved is reported as a cancelled operation, not a rollback failure. Any backup created for that attempt is retained.
- After a successful commit, inability to clean up transaction staging is logged as a warning; it does not misreport a committed deletion as failed.

## Focused logs

All events remain in `storage/logs/core.log` and matching events are additionally routed to focused logs: `api.log`, `storage.log`, `universe-deletion.log`, `backups.log`, `security.log`, `php-errors.log`, and `errors.log`. Deletion and recovery errors point to `storage/logs/universe-deletion.log` and `storage/logs/backups.log`.

## Validation (0.1.39)

- Browser QA: 106 passed, 0 failed.
- PHP CLI tests: 17 passed, 0 failed.
- PHP syntax checks and JavaScript syntax check passed.
- ZIP integrity verified.

## Upgrade safety

The package includes `storage/.htaccess` to deny direct HTTP access to live records, logs, backups, and recovery transactions, plus empty placeholders in `storage/data/`, `storage/logs/`, and `storage/backups/`. **Preserve the live server's existing contents of all three directories when upgrading.** The `.transactions` directory is created under `storage/data/` only while a deletion transaction is in progress; do not remove it manually if a critical recovery log references it.
