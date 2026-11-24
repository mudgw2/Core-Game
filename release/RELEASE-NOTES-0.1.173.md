# CORE 0.1.173 — Constellation Map Visibility

- Adds a directly rendered SVG constellation-map layer above the Babylon.js canvas so generated star systems and navigation links remain visible when the WebGL scene fails to display in the browser.
- Uses each generated System's existing `x` and `y` coordinates, normalized to the map viewport.
- Adds selectable, labeled System markers; selecting a marker uses the existing `selectSystem` workflow and updates the selected marker highlight.
- Retains the Babylon.js renderer and camera controls beneath the map layer.
- Keeps the cumulative 0.1.171 and 0.1.172 runtime safeguards.
