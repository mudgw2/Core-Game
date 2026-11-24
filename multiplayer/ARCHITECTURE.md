# CORE Multiplayer Architecture

## 1. Responsibilities

| Layer | Owns |
|---|---|
| PHP / Apache | Account sessions, account ownership, character lifecycle, universe assignment, persistent game state |
| Node.js / Colyseus | WebSocket sessions, matchmaking, room lifecycle, transient player presence |
| Babylon.js | Local rendering, input collection, deterministic environment rendering, remote entity interpolation |
| Existing generators | Deterministic universe/system/world generation from CORE's saved universe seed and records |

Colyseus is a separate real-time service. It does not replace CORE account,
character, universe, or JSON persistence systems.

## 2. Room identity and matchmaking

The room key is the tuple:

- `universeId`: stable saved-universe ID, never the seed.
- `systemId`: stable system ID within the universe.
- `locationId`: optional stable world/station/city/outpost ID.

The Colyseus 0.16 core definition uses
`filterBy(["universeId", "systemId", "locationId"])`. The system overview
uses an empty `locationId` and joins at system scope. The planetary test world
uses its deterministic generated world ID as `locationId`; PHP validates that
ID against the character’s current system before issuing the grant. This is a
room identity, not yet a persistent character surface-location record.

A full room can cause Colyseus to create another room instance with the same
matchmaking key. Capacity is configurable (default 16, range 1–64); this does
not guarantee one global room per location. A later scaling design must define
instance/shard behavior.

## 3. Server process and health

- Independent Node.js process under `multiplayer/`.
- Default bind address is `127.0.0.1:2567`.
- `GET /health` provides a minimal process health response.
- Room type `universe` is registered once at startup.
- Graceful shutdown uses Colyseus' default signal handling.
- No room-listing, monitoring, playground, or administrative endpoint is enabled.

## 4. Authentication boundary

Production joins require a short-lived PHP-signed HMAC-SHA256 grant verified by the Node service. The grant binds account, character, universe, system, and location identity. Do not forward the PHP session cookie to Node, and do not trust client-provided account, character, universe, or location identifiers as identity.

For isolated local development only, optional guest access can be enabled when
all of these conditions hold:

1. `NODE_ENV` is not `production`.
2. The listener host is loopback (`127.0.0.1`, `::1`, or `localhost`).
3. `CORE_MULTIPLAYER_ALLOW_DEV_GUESTS=true`.
4. `CORE_MULTIPLAYER_DEV_TOKEN` contains at least 32 characters.
5. The client supplies that token through the Colyseus authentication token.

This mode is not a substitute for PHP authentication and must never be exposed
through a public interface or reverse proxy.

## 5. Room lifecycle and presence

- `onCreate` validates and normalizes the room identity and applies capacity.
- `onAuth` verifies the PHP grant and requires an exact universe/system/location match; guarded local development-token access remains loopback-only.
- `onJoin` creates transient in-memory presence and sends a snapshot to the joining client.
- `onLeave` removes that session and broadcasts the departure.
- Room metadata reports room identity and current player count.
- `onDispose` clears transient presence and the simulation interval.
- The room broadcasts authoritative movement transforms; no player position is persisted to PHP storage.

## 6. Deterministic environment and netcode

Clients render the local environment from CORE's existing seeded generators. Colyseus synchronizes player activity and later shared mutable entities, not static generated geometry. For planetary movement, the server is authoritative: clients submit bounded input intent only, and never submit world coordinates or transforms.

## 7. Authoritative planetary movement (0.1.113)

- The planetary test world joins a room scoped to the assigned universe, current system, and generated world ID. PHP verifies that the requested world is part of the character's current generated system before signing the grant.
- The HMAC grant binds `locationId` as well as character, universe, and system. A grant for one world cannot join another world's room.
- Clients send only `forward`, `strafe`, `running`, and a monotonic input sequence. Client coordinates and transform targets are never accepted.
- The room validates each input, applies a minimum input interval, stops stale input after 250 ms, simulates at 20 Hz, normalizes diagonal movement, caps elapsed simulation time, and clamps positions to the test-world bounds.
- Server transform messages are transient. They are not persisted to character records. The existing local-only test-world movement remains available if multiplayer is not configured or the connection cannot be established.
- Remote player transforms and presence are received by clients. CORE 0.1.114 renders remote humanoids and interpolates authoritative transforms; no player position is persisted.

## 8. Remote player presentation (0.1.114)

- The PHP grant carries only a validated character appearance tint in addition to the existing authorization identity. Node validates the optional tint claim before accepting the grant.
- Room snapshots and transform updates include display name, tint, and the current run state. Account IDs and other character-record fields are not broadcast.
- The planetary Babylon.js client creates one remote humanoid per other session using the shared GLB model, exact Walk/Run/Idle clips where present, and a primitive fallback if the GLB cannot load.
- Remote nameplates are billboarded above avatars. Remote position and yaw are smoothed between authoritative server updates; yaw uses shortest-path interpolation. The local avatar also eases toward authoritative transforms.
- Remote scene resources are disposed on leave and disconnect. All positions remain transient room state.

## 9. Deployment

Keep the service on loopback/private networking behind a TLS-enabled reverse
proxy. Permit WebSocket upgrades only on the dedicated multiplayer endpoint.
Keep signing secrets in process environment variables, never in browser code,
PHP views, TypeScript source, `.env.example`, or player JSON. Add explicit
origin, rate, and payload controls before public gameplay connections are
enabled.

## 10. Release sequence

- 0.1.108: service boundary and identity/netcode contracts.
- 0.1.109: Colyseus process, health endpoint, room matching and lifecycle.
- 0.1.110: dependency security cleanup and WebSocket transport configuration.
- 0.1.111: PHP-issued short-lived grant and Colyseus verification.
- 0.1.112: Babylon.js client connection and connection lifecycle.
- 0.1.113: world-scoped authenticated rooms, synchronized player presence, and server-authoritative movement simulation.
- 0.1.114: remote player avatar rendering, appearance tint, nameplates, and visual interpolation.
- 0.1.115: basic chat.

## 11. Dependency security baseline (0.1.110)

The multiplayer package depends directly on `@colyseus/core` and
`@colyseus/ws-transport`, rather than the umbrella `colyseus` package. The
umbrella package brought in optional auth/tooling dependencies that were not
used by CORE and expanded the dependency tree into `grant`, `elliptic`, and
`request-oauth`/`uuid`. CORE does not use Colyseus Auth; PHP remains the account
authority and the future grant verifier will use Node's built-in `crypto`.

The `@colyseus/core` NanoID dependency is overridden to the patched 3.x line
(`^3.3.18`). The 0.1.110 patch also configures ping interval and retry limits
through `WebSocketTransport`, instead of passing transport-only options to the
legacy Server constructor. This is a constrained 0.16-compatible update, not
a major-version migration.

After installation, run `npm ls @colyseus/core @colyseus/ws-transport nanoid
elliptic uuid grant request-oauth` and `npm audit`. The actual resolved graph
and audit result on the host are authoritative; the patch build environment
does not have registry access and cannot certify the host's resolved tree.

- 0.1.112: Babylon.js gameplay can obtain a PHP grant and connect/disconnect from its authorized system-level Colyseus room; room migration follows successful travel. Presence only.
- 0.1.113: the planetary test world requests a location-bound PHP grant, joins a room keyed by universe/system/world, sends bounded directional intent, and receives server-simulated transforms. Position remains session-only and is not written to PHP character storage.
- 0.1.114: the world client renders remote humanoids and nameplates, applies validated appearance tint from the PHP grant, and interpolates local/remote authoritative transforms.


## 9. Shared universe visitation (0.1.115)

- A character's `universe_id` remains its permanent home universe.
- `active_universe_id` is present only while visiting a shared universe.
- The owner generates a random 32-character share code; only its SHA-256 hash is persisted on the universe record. Rotating the code invalidates the prior code.
- Visitors must authenticate as the owner of the selected character and provide the current share code.
- The visitor's home system/location is snapshotted and restored on exit.
- Player gameplay services, authorization, and multiplayer grants resolve the active universe without overwriting home identity.
- Shared universe access does not merge accounts or character records. Character inventories and progression remain character-owned.

## 10. Hierarchical procedural seeds (0.1.115)

- Universe seed determines the generated universe baseline.
- Every generated system receives a stable `SYS-` seed. Legacy systems derive this value deterministically at runtime from the universe seed and stable system ID; saved universe records are not rewritten.
- Every generated world receives a distinct `WLD-` seed derived from its parent system seed and orbital slot.
- UWP is generated from the individual world seed; the world record also exposes its parent `system_seed`.
- Seeds are generation identity; persistent gameplay state remains separate and is not overwritten by regeneration.
