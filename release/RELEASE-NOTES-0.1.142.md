# CORE 0.1.142 — Planetary Controls and Map HUD Corrections

This focused release corrects the previously reported planetary interface defects.

## Character and inventory shortcuts

- `C` opens Character Information; pressing `C` again closes it.
- `I` opens Inventory; pressing `I` again closes it.
- Pressing the other shortcut switches directly to that panel.
- Key repeat is ignored for these interface shortcuts.

## Planetary map

- `M` toggles the map HUD.
- The map is a frameless, transparent overlay rather than a modal window.
- The map does not intercept movement or camera input. Its close button remains interactive.
- Movement remains enabled while the map is visible.

## Map position

The player spawns at 0° latitude / 0° longitude. The global map projection maps those coordinates to its exact center (360, 180 in the 720 × 360 SVG viewBox), so the initial player marker is centered.

## Scope and safety

No generation rules, fingerprints, saved universe data, character records, persistent geography, or storage content are changed. The release includes only the files listed in the patch ZIP.

## Validation limitation

Automated source and syntax checks can verify the implementation contract, but cannot prove the live browser behavior. Browser interaction testing remains required after installation.
