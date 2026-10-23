# CORE PHP Foundation 0.1.12

CORE 0.1.12 adds the first event processor and a derived notes projection while preserving the append-only event history and revision-checked storage transaction.

## Install
1. Back up the project directory.
2. Copy the contents of this package into the existing CORE project directory, overwriting matching application files.
3. Preserve all existing `storage/data/` and `storage/logs/` contents.
4. Open the Workbench and run automated QA.

## Event processing
- `note_added` events are validated and applied to `state.projections.notes`.
- Projected notes contain an ID matching the source event, text, revision, and creation timestamp.
- Every newly processed event adds a processing record with `applied` or `unhandled` status and a handler version.
- Unknown but syntactically valid event types remain in append-only history and are marked `unhandled`; they do not silently imply gameplay effects.
- Empty note text is rejected before the transaction commits, so event history, revision, and projection remain unchanged.
- Notes are limited to 500 Unicode characters.

The event handler runs within the same locked read-modify-write transaction as the history append. If validation or processing fails, the record is not committed. Existing event history and existing universe records are preserved; the new projection is created when new events are processed.

## Workbench and API
Use the browser Workbench to create/load a universe, append a change through the development-only Universe State API panel, and inspect the returned `state.projections.notes` and `state.event_processing` fields. The sample `note_added` change should create a note projection. Use a test universe when experimenting because changes persist.

`POST api/universe-state.php` accepts JSON like:

```json
{
  "universe_id": "<stored universe id>",
  "expected_revision": 0,
  "change": {"type": "note_added", "value": "A test note"}
}
```

The state-mutation endpoint is restricted to development mode and local/private-network clients. CORE does not yet have accounts, authentication, or authorization. Do not expose the application publicly.

## Automated QA
The Workbench browser suite runs **18 automated checks**, including event projection, transaction rollback for invalid note events, and handling of unknown event types. Tests use isolated temporary directories and do not modify the application's saved universes. The optional CLI runner remains available with `php tests/run.php`.

## Persistence and logging
- JSON writes use same-directory temporary files and atomic rename.
- Per-record locks serialize writes and read-modify-write transactions.
- Universe updates require an expected current revision.
- Runtime errors, exceptions, fatal errors, validation failures, and storage errors are logged to `storage/logs/core.log` where possible.

## Limitations
This is an initial event-processing framework, not a complete gameplay engine. Only `note_added` has a handler. Other event types are retained and marked `unhandled`. Accounts, authentication, authorization, multi-user ownership, and game mechanics remain unimplemented.
