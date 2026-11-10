# CORE 0.1.85 — Account Authorization

## Scope

This release adds a centralized authorization layer to player-facing gameplay APIs while preserving the existing private-network development Workbench.

## Changes

- Added `Core\Http\PlayerApiSecurity` to centralize session authentication, character ownership, universe membership, and CSRF validation.
- Added a Router pre-dispatch guard so authorization runs before gameplay handlers.
- Protected character record reads, character collections, scanning, crafting, fitting, discovery investigation, movement, travel, and character lifecycle actions.
- Restricted universe navigation and lifecycle APIs to universes with at least one character owned by the signed-in account.
- Restricted API character creation to the signed-in account; creating directly in an existing universe requires ownership of a character assigned to that universe.
- Filtered character collection responses to the signed-in account outside the trusted development Workbench.
- Added `X-CORE-CSRF` validation for authenticated API writes and updated the gameplay client to send the session token.
- Kept the development/private-network Workbench administrative bypass intact.
- Kept permanent character deletion unavailable to player sessions.
- Advanced the application and Workbench cache version to `0.1.85`.

## Validation

- CLI QA: 76 passed, 0 failed.
- Browser QA endpoint: 136 passed, 0 failed.
- PHP syntax: all project PHP files passed.
- JavaScript syntax: all 4 project JavaScript files passed.

## Installation

Extract the patch ZIP into the CORE project root. Back up the existing source first. Do not overwrite `storage/data`, `storage/logs`, `storage/backups`, or `.git`.
