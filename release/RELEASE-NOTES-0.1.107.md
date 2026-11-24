# CORE 0.1.107 — Complete Borderless UI Pass

## Problem corrected
CORE 0.1.106 did not fully resolve the border-removal request. Several player pages referenced `player.css` without a release-version query, allowing browsers to keep using a cached stylesheet. Component-level border declarations also remained in the shared stylesheets, and the standalone account-administration page maintained its own embedded CSS.

## Changes
- Added `assets/borderless.css` as the single final stylesheet on every CORE HTML surface: workbench, public landing page, account access, character selection, character creation, universe creation, gameplay, test-world view, and standalone account administration.
- Added the release version to every player stylesheet URL so updated CSS cannot silently remain behind a cached prior version.
- Removed component border declarations from the shared player, world, system-view, workbench, and standalone account-administration stylesheets.
- Removed the previous deliberate border exceptions from the landing-page orbital illustration and welcome decoration. Orbital artwork is retained as a background graphic rather than a CSS border.
- Removed the border-like inset stripe from the current player-workflow step and selected character-admin table row.
- Moved standalone account-administration styles from an embedded `<style>` block into `assets/admin-player-account.css`.
- Moved character-roster delete-button styling into the shared player stylesheet.
- Added browser-native form-control pseudo-element border resets for sliders, color controls, search controls, date controls, and file selectors.
- Kept keyboard navigation visible with a shared `:focus-visible` outline; mouse-focus outlines are suppressed.
- Replaced the previous superficial QA checks with checks for actual component declarations, all page stylesheet references, cache-busting, and keyboard focus styling.

## Verification
- CLI suite: **104 passed, 0 failed**.
- Browser QA endpoint: **155 passed, 0 failed**, HTTP 200.
- PHP syntax: **94 files passed**.
- CSS parsing: **6 stylesheets parsed without syntax errors**.
- Stylesheet URL checks: all 6 CSS assets returned HTTP 200.
- Source audit: no component border declarations remain outside the central `assets/borderless.css` contract.
- Representative UI components were rendered for visual review using WeasyPrint. Browser-native screenshot QA was not completed because Chromium would not start in the execution environment.
- No JavaScript files changed.

## Data and compatibility
- No storage schema, player data, account data, or universe data changes.
- No migration required.
- No player records, universe records, credentials, backups, or logs are included.
