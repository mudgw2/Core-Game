# CORE 0.1.109 — Colyseus Server and Universe Room Lifecycle

## Source baseline
- Built from the accepted CORE 0.1.108 source: user-confirmed PASS, 156 tests.
- No player storage, account records, universe records, credentials, backups,
  or logs are included or modified.

## Changes
- Added a runnable Node.js/TypeScript Colyseus 0.16 server process in
  `multiplayer/`.
- Registered the `universe` room type with matchmaking filters for stable
  `universeId`, `systemId`, and `locationId`.
- Added room identity validation, normalization, and collision-safe tuple keys.
- Added room lifecycle hooks for create, authentication, join, leave, and
  disposal.
- Added transient in-memory player presence keyed by Colyseus session ID.
- Added room metadata with identity and current player count.
- Added `GET /health` with service/release/authentication status.
- Added configurable host, port, and room capacity (default 16; range 1–64).
- Default bind address is loopback (`127.0.0.1`).
- Added opt-in development guest authentication, guarded by all of: non-
  production environment, loopback listener, explicit enable flag, and a
  configured token of at least 32 characters.
- Added six Node runtime tests for configuration restrictions, identity
  normalization, collision-safe room keys, and invalid configuration.
- Updated project state and installation instructions.

## Security and scope boundaries
- Production room joins are rejected until PHP-issued HMAC-SHA256 grant
  issuance and verification are implemented in 0.1.110.
- Development guest mode is for isolated loopback testing only; never expose it
  through a public listener or reverse proxy.
- Room presence is transient and server-side only. It is not Schema-synchronized
  or persisted in this release.
- No Babylon.js client connection, movement, chat, or persistent game mutation.
- Existing PHP account/session, character, universe, and JSON persistence
  systems remain unchanged.

## Verification
- CLI suite: **105 passed, 0 failed**.
- Browser QA endpoint: **157 passed, 0 failed**, HTTP 200.
- Node runtime contract tests: **6 passed, 0 failed**.
- PHP syntax: **94 files passed**.
- TypeScript source checked using a temporary Colyseus/Express declaration shim.
  This checks source-level typing against the documented 0.16 API shape but is
  not equivalent to compiling against installed package declarations.
- `npm install` timed out in the build environment, so dependency-backed
  `npm run build`, `npm test`, and a live Colyseus server/health request were
  not completed here. Run these on the Node.js host during installation QA.
- ZIP integrity and source/data exclusion audit passed.

## Compatibility
- Node.js 20+.
- Colyseus `^0.16.0`.
- Express `^4.21.2`.
- No PHP storage schema or player-data migration.
