# CORE 0.1.169 — Moon Depth-Test and Render-Order Safeguard

- Preserves 0.1.168 geometric-horizon moon placement.
- Makes moon materials explicitly opaque and self-lit.
- Bypasses scene depth testing for moon presentation meshes after their rays have been constrained above the geometric planetary horizon.
- Moves moon meshes to rendering group 2 so they render after terrain and ordinary world geometry.
- Adds diagnostics for active-camera identity, material alpha/depth settings, and mesh geometry/index counts.
- No generated moon records, orbital timing, terrain, camera controls, movement, or jump behavior changed.

Browser visual verification has not been performed by the assistant. User confirmation remains the acceptance test.
