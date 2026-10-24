# CORE PHP Foundation 0.1.32 — Universe Backup & Recovery

CORE is a seed-driven sci-fi sandbox foundation using PHP, JSON, and flat-file persistence.

## Universe backups and recovery (0.1.32)

Use Workbench → Data Management to create a checksum-verified snapshot of a universe and all characters associated with it. Snapshots are stored separately in `storage/backups/`, never include operational logs, and are listed in the workbench. Restore requires typing `RESTORE`, verifies the SHA-256 checksum, restores missing records, accepts already-identical records, and refuses to overwrite conflicting records.

Universe deletion remains preview-only. This release adds recovery foundations but does not yet enable cascading universe deletion. Keep off-host backups as well; local backups do not protect against disk or host loss.

## Installation

Deploy application files using your existing workflow. Preserve `storage/data/`, `storage/logs/`, and `storage/backups/`. Package placeholders do not replace real saved data. Backup management is restricted to development mode and local/private-network clients. Disable development QA and management endpoints before public exposure.

See `RELEASE-NOTES-0.1.32.md`.
