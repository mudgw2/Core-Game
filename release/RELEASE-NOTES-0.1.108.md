# CORE 0.1.108 — Colyseus Multiplayer Foundation

## Source baseline
- Built from user-supplied CORE 0.1.107.
- Previous user-confirmed baseline: CORE 0.1.106 — 154 tests passed.
- CORE 0.1.107 was supplied as the source archive for this update; its installation QA was not confirmed in this conversation.
- No persistent data files are included or modified.

## Changes
- Added an isolated `multiplayer/` Node.js/TypeScript project boundary.
- Declared Colyseus `^0.16.0` and Node.js 20+.
- Added shared TypeScript contracts for verified player identity, matchmaking
  room identity, and server-authoritative movement protocol.
- Distinguished the saved universe ID from its deterministic generation seed.
- Documented the current CORE source model: PHP sessions, CSRF, account-owned
  characters, assigned universes, current system IDs, deterministic world data,
  and Babylon.js client initialization.
- Defined future room matching as universe + system + optional persistent
  location identifier.
- Defined a short-lived HMAC-signed PHP-to-Colyseus authentication grant.
- Defined input-intent movement rather than trusting client-submitted positions.
- Added environment template and ignore rules for secrets/build output.
- Added CLI and browser QA checks for the multiplayer contracts.

## Deliberately not enabled
- No Colyseus server listener or Room implementation.
- No PHP token-issuing endpoint.
- No token verification or shared signing secret.
- No Babylon.js Colyseus client.
- No player position synchronization or chat.
- No change to existing single-player gameplay.

These are staged as separate future releases to protect the current game.

## Verification
- Existing CLI suite plus new foundation contract test.
- TypeScript contract compilation.
- Runtime test of room-key normalization and required identifiers.
- Browser QA endpoint plus new contract test.
- PHP syntax checks.
- ZIP integrity and source/data exclusion audit.

Exact counts are reported with the delivered patch after tests are run.
