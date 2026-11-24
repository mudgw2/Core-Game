# CORE 0.1.114 — Remote Player Rendering and Movement Interpolation

## Changes
- Added remote Babylon.js humanoid instances for other players in the same world-scoped universe room.
- Remote avatars load the shared `core-humanoid.glb`, use the exact Walk/Run/Idle clips where available, and fall back to a capsule-and-sphere model if the GLB cannot load.
- Added floating nameplates and character appearance tint. PHP derives the tint from the authenticated character record, validates it, and includes it in the five-minute HMAC grant. Node validates the optional claim and publishes only the tint with transient player presence.
- Added smoothed interpolation for remote position and yaw, including shortest-path angular interpolation. The local character now eases toward authoritative server transforms rather than snapping.
- Remote avatars and their animation/nameplate resources are cleaned up when players leave or the room disconnects.
- Corrected the system overview's room status to use Colyseus `roomId`, avoiding the previous `room undefined` display.
- Updated the PHP QA suite and multiplayer token tests for the new presentation contract. No persistent player data, account data, or universe data is changed.

## Verification
- Multiplayer TypeScript build: PASS.
- Multiplayer Node tests: 20 passed, 0 failed.
- PHP project QA: 109 passed, 0 failed.
- PHP syntax checks: PASS.
- Browser JavaScript syntax checks: PASS.
- Actual two-browser visual verification and live deployment QA remain required. The code tests do not prove that two public browser sessions render and interpolate each other correctly.

## Deployment
- Update the PHP application files and rebuild/restart the multiplayer service using the existing deployment procedure.
- No new ports, listeners, firewall rules, or environment variables are introduced.
- Existing HMAC secret and public WebSocket endpoint configuration remain unchanged.
