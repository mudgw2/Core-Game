# CORE PHP Foundation 0.1.8

This release strengthens JSON persistence while preserving the CORE Workbench and universe generation behavior.

## Install
Copy the contents of this folder into the existing CORE project directory, overwriting matching foundation files. Preserve unrelated files. **Do not replace or delete existing `storage/data/` or `storage/logs/` contents.** Back up the project before upgrading.

## Workbench
Use Check Server, Preview Universe, Create / Load, and **Run automated tests** in the browser. The Workbench activity log records browser actions and client-side failures.

## Persistence and logging changes
- Writes serialize to a temporary file in the target directory and replace the JSON record with a same-directory rename.
- Writes to the same record are serialized with a per-record lock file (`<record>.json.lock`). Lock files are expected and should not be manually removed while CORE is running.
- The writer checks complete writes, flushes the file, and calls `fsync()` when supported by the PHP runtime.
- Corrupt JSON, failed reads, and storage write failures raise explicit errors; storage errors are logged where possible.
- Existing centralized PHP runtime, exception, fatal-error, and validation logging remains enabled.

## Automated QA
The browser QA panel now runs nine tests, including complete JSON round-trips, record-lock/temp-file checks, and corrupt JSON rejection. Tests use temporary directories and do not modify the application's universe data. The optional CLI runner also checks PHP syntax and core storage behavior: `php tests/run.php`.

The browser QA endpoint is restricted to development mode and local/private-network clients. Set `qa.enabled` to `false` in `config/app.php` before exposing CORE publicly. This is a development utility, not a production-safe test service.

## Limitations
Per-record write locks prevent simultaneous writes from interleaving, but they do not implement transactional read-modify-write semantics across the entire universe service. Concurrent create/load requests and multi-file transactions need further testing before public deployment. CORE remains a development scaffold and should not be exposed publicly without authentication and a security review.
