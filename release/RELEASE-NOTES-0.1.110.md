# CORE 0.1.110 — Colyseus Dependency Security and Transport Configuration

## Source baseline
- Built from the user-confirmed CORE 0.1.109 source.
- User-reported 0.1.109 QA: 157 tests passed; Node TypeScript build and six Node tests passed; Colyseus service started successfully on loopback.
- No player storage, account records, universe records, credentials, backups, or logs are included or modified.

## Changes
- Replaced the umbrella `colyseus` dependency with direct `@colyseus/core` and `@colyseus/ws-transport` dependencies, remaining on the 0.16-compatible API line.
- Changed server and room imports to use `@colyseus/core` directly.
- Removed the unused Colyseus Auth package path. CORE already owns account sessions in PHP and does not use Colyseus Auth/OAuth. This removes the dependency path that introduced `grant`, `elliptic`, and `request-oauth`/`uuid` into this service's package tree.
- Added an npm override requiring `@colyseus/core` to resolve NanoID from the patched `^3.3.18` line.
- Moved WebSocket ping interval and retry settings onto `WebSocketTransport`, resolving the deprecation warning from the 0.1.109 runtime.
- Added dependency-manifest assertions to PHP QA and Node tests.
- Updated project state, installation guidance, and dependency-security documentation.

## Security notes
- This patch does not use `npm audit fix --force` and does not jump to Colyseus 0.18.
- It retains the Colyseus 0.16 API line to avoid an unrequested major-version migration.
- The patch build environment has no npm registry access. The exact resolved dependency graph and `npm audit` result must be verified on the host after `npm install`; this release does not claim a clean audit before that host-side check.
- If `elliptic`, `grant`, `request-oauth`, or `uuid` still appear after installation, retain the output of `npm ls` and `npm audit` for the next targeted remediation.

## Verification in this build environment
- PHP syntax and CORE CLI/browser QA are run against this exact patch source where the environment permits; see the accompanying build report.
- Dependency-backed TypeScript compilation and live Colyseus startup cannot be certified here because npm registry access is unavailable.
- User-host validation is required: `npm install`, `npm run build`, `npm test`, `npm ls @colyseus/core @colyseus/ws-transport nanoid elliptic uuid grant request-oauth`, and `npm audit`.

## Compatibility
- Node.js 20+.
- `@colyseus/core` `^0.16.26`.
- `@colyseus/ws-transport` `^0.16.0`.
- Express `^4.21.2`.
- No PHP storage schema or player-data migration.
