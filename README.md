# CORE PHP Foundation 0.1.19 — Multi-Hop Travel

CORE is an early browser-based sci-fi sandbox foundation using PHP, JSON persistence, HTML, CSS, and JavaScript.

## What's new in 0.1.19

- Start a travel route from a character's current system to any system in the same universe.
- Execute travel one connected jump at a time using **Travel next jump**.
- Each successful jump updates character location, revision, and character exploration history.
- Route progress tracks the saved path, current step, hop count, and active/completed state.
- Stale revisions are rejected; completed routes cannot be advanced again.
- Manual movement cancels an active multi-hop route so the old route cannot be mistaken for a valid continuation.
- The Workbench displays route progress and the next system.
- Browser QA now covers route start, one-hop execution, final destination, stale revisions, and cancellation after manual movement.

## Install

1. Extract the ZIP.
2. Copy the package contents into the existing CORE project directory, overwriting matching application files.
3. Preserve your existing `storage/data/` and `storage/logs/` contents. This package contains only `.gitkeep` placeholders in those directories.
4. Open the Workbench and click **Run automated tests**.

## Try multi-hop travel

1. Create or load a universe and select a character.
2. Choose a distant destination under **Route destination** and click **Plan route** to preview it.
3. Click **Start travel along route**. The character remains in the starting system while the route is stored.
4. Click **Travel next jump** to execute one jump. Each click advances one connected hop, updates location and exploration history, and increments the character revision.
5. Repeat until the Workbench reports that travel is complete.

If you use the regular **Move character** control while a route is active, CORE cancels that route. Plan and start a new route to continue.

## Current limitations and security

This is a development foundation, not a finished game. Character accounts, ownership, authentication, and authorization are not implemented. Character, navigation, travel, and browser QA endpoints are restricted to development mode and local/private-network clients. Do not expose this application publicly until proper authentication and access controls are implemented.

All browser QA tests use isolated temporary storage and should not modify saved universe data.
