# CORE 0.1.112 — Babylon.js Colyseus Client Integration

## Changes
- Added a browser-side Colyseus 0.16 SDK integration to the existing Babylon.js gameplay page.
- Added explicit Connect/Disconnect controls and connection status.
- Browser requests a fresh five-minute grant from the existing PHP token endpoint; the grant is assigned to the Colyseus SDK auth token and is never stored in local storage.
- Joins the authorized universe/system room using the saved universe ID and current system ID.
- On successful interstellar travel, an active room connection is migrated to the character's new current system using a newly issued PHP grant.
- Leaves the room on page exit and handles room leave/error callbacks.
- Multiplayer browser URL is supplied by the PHP environment variable `CORE_MULTIPLAYER_PUBLIC_URL`; invalid URLs are ignored.
- Presence connection only: no synchronized movement, character transforms, combat, or persistent multiplayer state is introduced.

## Deployment configuration
Set `CORE_MULTIPLAYER_PUBLIC_URL` in the PHP web-server environment to the browser-reachable Colyseus URL, e.g. `wss://your-host.example/multiplayer` when a TLS reverse proxy routes that path to the loopback Node service. The default Node listener remains `127.0.0.1:2567`; do not expose it directly. Configure the reverse proxy and WebSocket upgrade forwarding separately.

The page loads the pinned Colyseus 0.16.22 browser SDK from unpkg. Babylon.js and Colyseus SDK delivery require browser network access to their configured CDNs.

## Verification
- Automated CLI, PHP syntax, and browser QA checks are included in this release.
- Live Apache/PHP environment, reverse-proxy WebSocket upgrade, and real browser room join require deployment-host verification.
