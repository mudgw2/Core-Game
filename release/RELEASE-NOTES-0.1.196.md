# CORE 0.1.196 — Local Planetary Relief and Surface Altitude

## Changes
- Adds a high-resolution, spherical local terrain patch around the player, rebuilt as the player travels.
- Adds deterministic kilometer-scale relief to local land-surface samples while retaining the existing persistent terrain definition and global terrain coloration.
- Fades the added local detail smoothly to zero at the patch boundary so the close-range patch blends back into the existing planetary surface.
- Uses the same detailed surface elevation for character spawn, movement, terrain sampling, and altitude reporting.
- Displays current surface altitude in meters in the location HUD and in meters/kilometers in the planetary map location readout.
- Preserves the existing celestial rendering implementation.

## Installation
Extract the ZIP into the existing CORE application root, preserving the included relative folder paths and allowing the files to overwrite their existing versions.

## Verification scope
Automated QA, PHP syntax checks, JavaScript syntax checks, and ZIP integrity were verified. Browser rendering and in-game visual behavior still require runtime confirmation.
