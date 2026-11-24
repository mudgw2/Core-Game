# CORE 0.1.113 — Server-Authoritative World Movement

## Changes
- Added a world-scoped multiplayer room identity using saved universe ID, current system ID, and generated world ID.
- Extended PHP-issued five-minute HMAC grants with a `locationId` claim. PHP regenerates the world's system profile and verifies the requested world belongs to the character's assigned universe and current system before issuing a grant.
- Node authorization now binds the grant to the exact requested world room; a grant cannot be reused for another world. System-level grants continue to use an empty location ID.
- Added bounded movement intent (`forward`, `strafe`, `running`, `sequence`) and server-side simulation at 20 Hz. The server normalizes diagonal movement, caps simulation delta, validates sequence/rate, stops stale input after 250 ms, enforces ±5,000-unit world bounds, and broadcasts authoritative player transforms. Walk speed is 3.2 units/second; run speed is 6.0 units/second.
- The planetary test world automatically connects when the public multiplayer URL and Colyseus SDK are available. It sends input intent and applies server transforms. When unavailable, local-only movement remains available and is labeled accordingly.
- Added room snapshots and join/leave/transform messages for synchronized presence. Remote avatar rendering/interpolation remains deferred.
- Player positions remain transient and are not persisted to character records. No account, character, universe, or saved player data is included.

## Deployment
- Keep `CORE_MULTIPLAYER_HMAC_SECRET` configured identically in PHP and Node; do not rotate or paste it as part of this update.
- Keep `CORE_MULTIPLAYER_PUBLIC_URL` configured in the PHP environment and the TLS WebSocket reverse proxy routed to `127.0.0.1:2567`.
- No new public listener or firewall opening is required.
- The browser loads the pinned Colyseus 0.16.22 SDK from unpkg; browser network access to the SDK CDN remains required.

## Verification
- Node TypeScript build and unit tests cover bounded input validation, speed calculation, diagonal normalization, delta caps, world bounds, HMAC location claims, and world-room authorization.
- PHP CLI tests and browser QA verify generated-world grant binding and the client/server movement contract.
- Actual two-browser movement, reverse-proxy WebSocket, and host environment behavior still require deployment-host QA.
