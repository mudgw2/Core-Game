# CORE 0.1.138 — Planetary Navigation and Gameplay Interface

## Added
- Expanded the Babylon.js terrain surface to cover the complete generated planetary hex map, with an additional terrain margin beyond the outermost hexes to avoid the previous small-patch cut-off.
- Blended terrain elevation and vertex colors across nearby geographic hexes.
- Added current-hex resolution from the character's live planetary X/Z position.
- Added the full planetary hex map overlay, opened with **M**, including terrain classifications, settlement/spaceport markers, and a live character-location marker.
- Added the **Esc** gameplay menu with Resume, Settings, Keybindings, Movement, and Exit Test World.
- Added **C** for Character Information and **I** for Inventory.
- Moved live coordinates to the upper-left HUD beneath the world's UWP/descriptor and removed the persistent upper-right Character box.
- Expanded the top header to show Universe, Constellation, System, and World context.

## Preserved
- Existing authenticated geography loading and persistent geography records.
- Existing character model loading, camera controls, local movement, multiplayer session flow, and server-authoritative movement behavior.
- Existing account, character, universe, and player data.

## Notes and limitations
- The map and terrain consume the same saved planetary geography record; the map is not a separately generated copy.
- The terrain margin extends beyond the outermost map hexes. This is a practical continuous-surface test implementation, not planetary curvature or an infinite-world streaming system.
- Settings are currently a placeholder section. Character information displays available safe character fields. Inventory opens, but the item/equipment system is not yet configured for this test-world interface.
- Character planetary location is still session-only and is not persisted by this release.
- Browser visual/interactive testing must still be performed in the user's environment.
