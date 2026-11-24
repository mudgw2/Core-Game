# CORE 0.1.111 — PHP Multiplayer Authentication

## Changes
- Added `POST /api/multiplayer/token`, protected by the existing PHP account session and CSRF token.
- PHP verifies account ownership, character assignment, current system, and character/universe lifecycle before issuing a five-minute HS256 grant.
- Added Node.js HMAC verification with constant-time signature comparison, issuer/audience/subject checks, expiry/issued-at validation, and a maximum 300-second lifetime.
- Colyseus universe room authorization now accepts verified PHP grants and binds the grant to the requested saved universe and system. Non-empty location rooms remain unauthorized until CORE defines persistent location IDs.
- Retained the loopback-only development guest mode.
- No player records, account records, passwords, backups, or generated universe data are included or modified.

## Configuration
Set the same `CORE_MULTIPLAYER_HMAC_SECRET` (at least 32 characters) in the PHP web process and Node service. Generate with `openssl rand -hex 32`. Keep it outside the web root and source control. See `multiplayer/README.md`.

## Verification
- PHP syntax: 94 files passed.
- PHP CORE QA: 105 passed, 0 failed.
- Node TypeScript build: passed.
- Node tests: 11 passed, 0 failed (including valid grant, invalid signature, expired grant, and invalid claim tests).
- Actual Apache environment configuration and end-to-end browser-to-Colyseus connection require deployment-host verification.
