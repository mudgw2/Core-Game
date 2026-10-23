# CORE PHP Foundation 0.1.5

This release makes the browser workbench the primary development/test interface and adds centralized server-side PHP error logging plus a visible browser activity log.

## Install
Copy the contents of this folder into the existing CORE project directory, overwriting matching foundation files. Preserve unrelated files.

## Workbench
Open the project root. Use Check Server, Preview Universe, and Create / Load. The Workbench activity log shows browser actions, failed HTTP responses, JavaScript errors, and rejected promises.

## Logging
Server log: `storage/logs/core.log` (JSON Lines, one event per line). Validation failures (including missing seed values) are logged at WARNING level with method, route, and error code. Unsuppressed PHP runtime warnings/notices, uncaught exceptions, fatal PHP errors, unmatched routes, and handled requests are also logged. Errors are also sent to PHP's `error_log`. Ensure the Apache/PHP user can write to `storage/logs/`. The activity log is browser-local and is not a replacement for the server log.

## Limitations
This is still a development scaffold. It does not yet provide an authenticated log viewer or complete coverage of errors in the host OS/Apache itself. Storage concurrency guarantees and authentication are not production-ready. Do not expose publicly.
