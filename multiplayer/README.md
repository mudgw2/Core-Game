# CORE Multiplayer — 0.1.115 Shared Universe Sessions

This is a separate Node.js service. It does not replace the PHP application or change JSON persistence. CORE 0.1.115 retains remote humanoid presentation and adds account-authorized shared-universe visitation while preserving each character home universe.

## Requirements

- Node.js 20 or newer
- npm 10 or compatible

## Install and build

```bash
cd multiplayer
npm install
npm run build
npm start
```

The default listener is `127.0.0.1:2567`. The health endpoint is
`http://127.0.0.1:2567/health`.

## Configuration

Copy `.env.example` to `.env` and provide values through the process manager or
your environment. The service intentionally does not load `.env` itself; use
your deployment's environment-file mechanism.

- `CORE_MULTIPLAYER_HOST`: listener address; defaults to loopback.
- `CORE_MULTIPLAYER_PORT`: listener port; defaults to 2567.
- `CORE_MULTIPLAYER_ROOM_CAPACITY`: per-room capacity; defaults to 16, allowed 1–64.
- `CORE_MULTIPLAYER_ALLOW_DEV_GUESTS`: must be `true` to permit development-only room joins.
- `CORE_MULTIPLAYER_DEV_TOKEN`: development token; minimum 32 characters.

Development guest access is forcibly disabled in production and unless the
listener host is loopback. This is not PHP authentication. Do not expose the
service publicly or enable development guest access on a public interface.

## Room matching and lifecycle

The `universe` room is matched by `universeId`, `systemId`, and `locationId`.
For the current CORE data model, send `locationId: ""` to join a system-level
room. Saved universe ID is used for matchmaking; the public universe seed is
not a room key or credential.

Room instances maintain transient server-side player presence and update room
metadata as clients join or leave. The world-scoped room accepts bounded
movement intent, simulates authoritative transforms at 20 Hz, and broadcasts
snapshots to connected clients. The Babylon.js world renders other players
using the shared humanoid GLB (with a primitive fallback), character tint, and
nameplate; remote transforms are smoothed between server updates. Presence and
positions remain transient and are not persisted. Room capacity can produce
multiple instances for one identity when full; this remains an intentional
limitation until an explicit shard/instance policy is designed.

## Health check

`GET /health` returns service status, release, room type, and whether local
development guest mode is enabled. It does not expose room listings or player
identities.

## Security boundary

- Production room joins require a valid PHP-issued HMAC grant. The browser requests a fresh grant from the authenticated PHP session before joining.
- Never pass account or character IDs as proof of identity.
- Do not commit `.env`, tokens, account data, character data, universe data,
  logs, or generated build output.
- Run behind a TLS-enabled reverse proxy when remote browser access is enabled.

## Dependency security (0.1.110)

The service imports `Server`, `Client`, and `Room` from `@colyseus/core` and
uses `@colyseus/ws-transport` directly. It intentionally does not install the
umbrella `colyseus` package or `@colyseus/auth`, because CORE does not use the
Colyseus OAuth/auth stack. The core NanoID dependency is constrained to the
patched `^3.3.18` line through npm overrides.

After `npm install`, inspect the actual dependency tree and audit it:

```bash
npm ls @colyseus/core @colyseus/ws-transport nanoid elliptic uuid grant request-oauth
npm audit
```

A clean audit is not claimed until these commands complete on the deployment
host. Do not use `npm audit fix --force`.


## PHP-issued HMAC grants (0.1.111)

PHP issues a five-minute HS256 grant at `POST /api/multiplayer/token`.
The request must use the signed-in PHP account session, include
`X-CORE-CSRF`, and supply the selected `character_id` in its JSON body.
PHP verifies account ownership, character/universe assignment, current system,
and enabled/unlocked lifecycle state before signing. The saved universe ID,
not the public seed, is used for room identity.

Set the same secret of at least 32 characters in both PHP-FPM/Apache and the
Node service as `CORE_MULTIPLAYER_HMAC_SECRET`. Generate one with:

```bash
openssl rand -hex 32
```

Do not put the secret in the web root, source control, `.env` committed to Git,
or browser JavaScript. Configure it through each service's protected
environment configuration. The token endpoint returns the grant and its
expiry; clients pass the grant as the Colyseus join token. The server verifies
HS256, issuer, audience, subject/character consistency, timestamps, and
five-minute maximum lifetime. A grant only authorizes its signed universe and
system; the current model permits system-level rooms (`locationId: ""`).

The service does not query PHP storage directly. Ownership and assignment are
validated by PHP at issuance; the short grant is then cryptographically
verified by Node. Immediate revocation is not provided in this release.


## Babylon.js browser client (0.1.112)

The gameplay page loads the pinned `colyseus.js@0.16.22` browser bundle and provides explicit Connect/Disconnect controls. Set `CORE_MULTIPLAYER_PUBLIC_URL` in the PHP web-server environment to the browser-reachable `ws://` or `wss://` endpoint. The client requests a new five-minute grant from PHP, assigns it to `client.auth.token`, and joins the `universe` room using the saved universe ID and current system ID.

An active connection is migrated to a newly authorized room after a successful system jump. The room currently tracks transient presence only: there is no player movement, synchronized transform, combat, or persistent multiplayer state yet. If the public URL is absent or invalid, gameplay remains available and the Connect button remains disabled.


## Authoritative planetary movement (0.1.113)

The planetary test world requests a five-minute PHP grant containing its generated world ID, then joins the `universe` room keyed by saved universe ID, current system ID, and world ID. PHP regenerates the worlds for the character's current system and rejects a world ID that is not present there. The HMAC grant binds that `locationId`, preventing a valid grant for one world from being reused in another.

The browser sends only bounded directional input (`forward`, `strafe`, `running`, and `sequence`). The server stops stale input after 250 ms, simulates movement at 20 Hz, and broadcasts authoritative transforms. It does not accept client-submitted coordinates. Positions and presence are transient room state and are not saved to character JSON. If the multiplayer URL or SDK is unavailable, the existing local-only test-world movement remains available and is labeled as local-only.

The world client now renders remote humanoids and nameplates, applies the character's saved appearance tint from the signed PHP grant, and interpolates remote and local authoritative transforms. Remote avatars are removed when their session leaves. The system overview presence panel also displays the Colyseus `roomId` rather than relying on the unsupported `id` property.


## Remote player rendering and interpolation (0.1.114)

The PHP grant now includes the selected character's validated six-digit appearance
tint. Node verifies the optional tint claim and includes it in the transient player
snapshot; no account or character record is exposed to other clients.

The planetary world creates a remote humanoid instance for each other session,
using the shared `core-humanoid.glb` model and exact Walk/Run/Idle clips where
available. If model loading fails, a capsule-and-sphere avatar is used instead.
A floating nameplate identifies each remote player. Server transform updates are
smoothed on the client, including shortest-path yaw interpolation. The local
character also eases toward authoritative server transforms rather than snapping
on each update. Remote models, animations, and labels are disposed on player
departure or room teardown.

The system-overview session indicator reads Colyseus `roomId` (with a defensive
fallback) so it no longer reports `room undefined`.


## Shared universe access (0.1.115)

The PHP application issues rotating share codes for a universe owner. Only a SHA-256 hash is stored. Another account can use a valid code with one of its own characters to visit the shared universe. The character retains its permanent `universe_id`; `active_universe_id` identifies the temporary visit. Leaving clears the active association and restores the saved home system/location. Gameplay APIs and PHP-issued multiplayer grants resolve the active universe, and account authorization verifies active shared access.
