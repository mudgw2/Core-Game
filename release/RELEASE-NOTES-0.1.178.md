# CORE 0.1.178 — Persistent Diagnostics

This release adds a shared diagnostics workflow so browser rendering failures and server-side errors can be investigated from concrete reports.

## Browser diagnostics
- Captures uncaught JavaScript errors, unhandled promise rejections, console errors, WebGL context loss/restoration, network online/offline changes, and page visibility changes.
- Registers the main Babylon.js scenes (Universe/Constellation, System, and Planetary) and captures their current state when a report is requested.
- Scene snapshots include camera position/target/direction and clipping range; mesh names, visibility, enabled state, positions, material alpha/depth settings, rendering groups, layer masks; and Babylon's active mesh list.
- Captures browser version/platform, viewport/device-pixel ratio, canvas sizes, WebGL renderer information, and recent diagnostic events.
- Diagnostics controls are available in gameplay navigation and on the full-screen Planetary Test World. They can download a JSON report locally or submit it to the CORE server.

## Server diagnostics
- Existing PHP runtime warning, uncaught exception, and fatal shutdown handlers continue writing structured JSON-line logs.
- Log files now rotate at 5 MB and retain three generations. Entries include CORE version, request ID, method/path, and a non-reversible session fingerprint for correlation.
- Submitted reports are stored under `storage/diagnostics/`, protected by the existing storage access-denial rule.
- Submission requires a signed-in account and valid CORE CSRF token. Reports are limited to 256 KB, throttled to one per 10 seconds per session, and retained as a rolling maximum of 100 files.

## Verification boundary
Automated source-regression and syntax checks can verify that capture hooks and protections are present. They cannot prove that Apache permissions permit writes, that a real browser report successfully reaches the server, or that a particular visual bug is diagnosed. Verify those in the installed CORE environment. A downloaded JSON report remains available if server submission fails.
