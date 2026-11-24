# CORE 0.1.204 — Terrain Streaming & Surface Rendering

## Included
- Local terrain patch sampling is processed in bounded 4,096-vertex batches across animation frames. The previous patch remains visible until replacement position/color buffers are ready.
- New requests coalesce to the latest player/camera position while a terrain build is in progress, avoiding repeated synchronous full-patch rebuilds during rapid movement.
- Ground coloration now includes deterministic multi-scale world-space variation. It adds surface breakup without increasing mesh density or requiring texture downloads.
- Ocean material has a stronger specular response, subtle emissive lift, and slightly more pronounced animated wave displacement.
- Ocean animation runs at a lower update cadence and no longer recalculates mesh bounds on every wave update; displacement remains within the existing mesh bounds.

## Preserved
- Existing procedural terrain seed and generator parameters.
- Existing authored terrain modifications and world geography.
- Current planet curvature, terrain relief scale, spawn validation, movement, run/jump, developer fly controls, and celestial rendering.

## Scope boundary
This release reduces synchronous terrain rebuild work and improves surface appearance. It does **not** yet implement server-backed persistent geographic chunks, predictive chunk streaming, or distance-based chunk LOD. The existing persistent planetary terrain definition remains authoritative; explored geographic chunk persistence is a subsequent storage/API milestone. No existing world data is migrated or rewritten.

## Verification
- PHP regression suite: 183 passed, 0 failed.
- JavaScript syntax check: passed.
- PHP syntax checks for changed PHP files: passed.
- ZIP integrity: passed.
- Browser frame-time and visual verification: pending user testing.
