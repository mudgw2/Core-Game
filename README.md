# CORE PHP Foundation 0.1.10

This release exposes the revision-checked universe-state operation through the Workbench and a development-only API endpoint.

## Install
Copy the contents of this folder into the existing CORE project directory, overwriting matching application files. Preserve unrelated files. **Do not replace or delete existing `storage/data/` or `storage/logs/` contents.** Back up the project before upgrading.

## Workbench
Use Check Server, Preview Universe, Create / Load, and **Run automated tests** in the browser. After Create / Load, the Workbench fills in the universe ID and current state revision. The Universe State API panel can append a JSON change event and updates the displayed revision after a successful commit.

**State changes modify the saved universe.** Use a test universe when experimenting. A change must be a JSON object with a non-empty string `type`, must be no larger than 16 KB, and must include the expected current revision. Stale revisions return a conflict and do not modify state.

## State API
`POST /api/universe/state` (explicit wrapper: `api/universe-state.php`) accepts JSON:

```json
{
  "universe_id": "universe-id",
  "expected_revision": 0,
  "change": { "type": "note_added", "value": "Example" }
}
```

Successful responses return the saved state and new revision. Stale revisions return HTTP 409 with `revision_conflict`; invalid input returns HTTP 400. All API errors are logged.

**Security restriction:** this endpoint is enabled only when `config/app.php` sets `app.environment` to `development` and the request comes from a loopback or private-network address. This is a development interface, not authentication. Do not expose CORE publicly; disable development mode and add real authentication/authorization before any public deployment.

## Automated QA
The browser QA panel runs eleven-plus tests (13 in this release), including persistence round-trips, duplicate-create protection, revision conflicts, required change type, payload size limits, and corrupt JSON rejection. Tests use temporary directories and do not modify application universe records. The optional CLI runner remains available: `php tests/run.php`.

## Persistence and logging
- JSON writes use same-directory temporary files and atomic rename.
- Per-record locks serialize writes and read-modify-write transactions.
- Universe updates require an expected revision to avoid stale overwrites.
- PHP runtime errors, exceptions, fatal errors, validation failures, and storage errors are logged to `storage/logs/core.log` where possible.

## Limitations
Universe state changes are currently append-only event records. They are not yet interpreted as gameplay effects. Full authentication, authorization, multi-user account ownership, and a public-safe deployment configuration are not implemented.
