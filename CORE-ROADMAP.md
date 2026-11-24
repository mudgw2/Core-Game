# CORE — Balanced Development Roadmap

**Last updated:** 2026-11-21  
**Last user-confirmed baseline before 0.1.155:** 0.1.154 — PASS, 162 tests

## Guiding approach

Move all major aspects of the game forward in small, coordinated amounts. Do not let one subsystem get so far ahead that integrating or recovering the others becomes difficult. Keep the game increasingly playable throughout development. Priorities below are a rolling plan, not fixed release promises.

## Active development tracks

1. **Universe and World:** richer world-level physical data and procedural detail; geography, atmosphere, climate, geology, hydrology, biomes, settlements, and resources.
2. **Astronomical and Surface Environment:** stars tied to system data; physical moons/satellites and orbital behavior; planetary lighting and persistent simulation time; terrain and environmental presentation.
3. **Character:** character details, attributes, skills, progression, equipment, and inventory integration.
4. **Gameplay:** movement, spherical jumping, scanning, exploration, resource discovery, and mining.
5. **Multiplayer:** developer-only test controls and repeatable tests for connected players, movement, coordinates, time, disconnect/reconnect, and persistence.
6. **Interface:** keep existing UI usable while systems evolve; later conduct a deliberate redesign using the user's Penpot files.

## Near-term sequence

### First coordinated pass — world, sky, movement
- Audit existing world profile and geography generation before expanding it.
- Extend world-level information incrementally, preserving deterministic seeds and separation between generated baseline and persistent discoveries/changes.
- Extend physical world profiles with deterministic, clearly labelled estimates; add deterministic multi-moon orbital records and clock-driven multi-moon presentation. (0.1.154)
- Continue the astronomical environment with better stellar configuration and lighting tied to actual system data.
- Add jumping with local spherical gravity/surface-normal behavior, not a flat-world vertical impulse.
- Keep character controls, planetary clock, lighting, map coordinates, and multiplayer movement consistent.

### Current coordinated slice — movement and world interaction (0.1.155)
- Spacebar jump now applies a gravity-driven visual arc along the local surface normal. In shared worlds, jump state is currently local presentation only; server replication and collision-aware physics remain open.
- `V` opens a player-facing World Environment briefing with currently available physical/environmental properties. Missing data is identified rather than fabricated.
- Night-sky star visibility has been strengthened; verify in browser runtime across daylight and night phases.
- Continue improving stellar positioning, moon visibility/orbits, and actual world environmental data/presentation.

### Second coordinated pass — character, skills, resources
- Audit character record structure and current scan/discovery/inventory/crafting services.
- Define a small initial skill framework and a few useful skills rather than a large catalogue.
- Build mining as the first skill/module proof of concept.
- Connect generated resource deposits, scanning/discovery, equipment, skill checks, extraction, inventory, and persistent deposit depletion.

### Third coordinated pass — multiplayer testability
- Add developer-only tools for spawning/connecting test participants, inspecting connected players and authoritative coordinates, teleporting/resetting test positions, setting simulation time, and simulating disconnect/reconnect where safe.
- Ensure controls are unavailable to ordinary players and do not bypass server authorization or production persistence protections.
- Use the tools for systematic multi-client testing.

### Ongoing — UI and integration
- Keep UI changes small and tied to real capabilities as they arrive.
- When Penpot design files are supplied, use them for a deliberate visual/interaction pass across HUD, character, skills, inventory, mining, world information, navigation, and menus.
- Continue iterating on character and environment after the first mining/multiplayer milestones.

## Explicitly deferred

- Full Technology Level advancement and simulation: retain TL as a cross-cutting future design constraint, but do not implement it yet.
- Large-scale skill catalogues before the initial skill framework and mining proof of concept are validated.
- Large asset-heavy world generation before deterministic data layers and fallback behavior are dependable.

## Completion philosophy

A milestone is not complete merely because code exists. It should have focused regression coverage, preserve existing player data, and be exercised in the running game where practical. Keep user-reported QA separate from assistant-run checks.


## 0.1.156 progress

- Character: connect existing GLB takeoff, airborne, and landing animations to spherical jump physics.
- Astronomy: replace sub-pixel star geometry with visible emissive sky sprites and explicit night visibility.
- Environment: increase deterministic terrain relief and terrain palette differentiation.
- Still requiring player runtime verification: jump animation quality, actual night-star visibility, and terrain appearance across different worlds.
- Continue balanced progress across world/environment, character/gameplay, multiplayer tooling, and UI rather than allowing one subsystem to dominate.


## 0.1.157 progress

- Replaced world-space night star meshes with a deterministic screen-space background layer to avoid clipping, depth, and camera-orientation failure modes. Confirm star visibility and day/night fading in the running browser.
- Enabled Babylon.js animation blending for locomotion and jump clips. Verify takeoff, airborne, landing, idle, walking, and running transitions in the running game.
- Continue the balanced world/environment, character/skills, mining, multiplayer testability, and UI tracks. Do not advance Technology Level simulation yet.


## 0.1.158 corrective focus

- Restore reliable imported GLB clip playback by removing the 0.1.157 per-group blending change.
- Validate the full standing/walking/running and takeoff/airborne/landing cycle in the browser. If abrupt transitions remain, investigate clip timing, target tracks, and explicit crossfade behavior separately rather than re-enabling unverified group blending.
- Preserve the screen-space night-sky implementation from 0.1.157 while jump animation is corrected.


## 0.1.159 progress — moon visibility and test coverage

- Moon presentation now uses a stable local sky frame initialized from the entry camera heading and planetary surface normal, rather than a global X/Z plane that could put moons behind terrain or outside the initial view.
- Increased moon visual scale/readability and widened the surface camera field of view to support the near-horizon sky arc.
- Added deterministic test-only satellites to the previously moonless World 01-3 (desert) and World 01-6 (asteroid) in Universe 1337 → Constellation 01 → System 01. Existing moons on the other four worlds are retained.
- User browser verification is still required; the release is not considered runtime-confirmed until the moons are visible in the running game.


## 0.1.160 progress — jump phases, camera inspection, and moon framing

- Correct multiplayer idle-state interference with jump clips and transition from takeoff to airborne at the physical apex.
- Add vertical camera orbit and an `F` close-view / third-person toggle; retain wheel zoom and terrain clearance.
- Raise the moon's deterministic visual sky arc to improve discoverability. Validate World 01-1 and the two 1337 test-moon worlds in-browser.
- Browser validation remains outstanding for jump transitions, camera controls, moon visibility, and Constellation 3D rendering. Continue to treat automated checks and user runtime confirmation as separate QA records.


## 0.1.161 corrective pass — first-person and lunar visibility

- Add a real eye-height first-person camera state, wheel-zoom entry/exit, and `F` mode cycling while retaining the established jump behavior.
- Reframe and strengthen moon meshes for the default surface-camera frustum; keep their generated orbital period/phase data intact.
- Runtime verification remains pending. If moons are still absent, inspect live scene mesh counts, boot-world moon records, and camera/frustum state rather than continuing blind visual tuning.


## 0.1.163 diagnostic focus — moon visibility
- Add live Babylon moon-mesh and camera-projection diagnostics to the developer World Information panel.
- Use actual runtime evidence to determine whether the remaining failure is missing world data, mesh creation/state, camera/frustum placement, or another rendering issue before changing moon presentation again.


## 0.1.164 corrective focus — keyboard shortcut compatibility

- Preserve native Ctrl+C / Cmd+C and other browser/OS modified shortcuts by ensuring gameplay keybindings ignore modifier-key combinations.
- Re-test copying diagnostic text from World Information; then use the report to continue the moon-rendering investigation.


## 0.1.165 corrective focus — camera-relative moon placement

- Rebuild the moon presentation tangent frame from the active camera direction each update so a stale initial heading cannot leave the moon behind the camera.
- Preserve deterministic generated moon data and simulation-clock-driven orbital parameters.
- User runtime verification is still required to confirm the moon is visible in the active world.


## 0.1.166 corrective focus — camera-view-aligned moon presentation

- Runtime evidence showed the camera looking approximately 44 degrees below the local planetary tangent while the previous moon arc remained near the horizon; both moons therefore projected outside the viewport.
- Use the active camera's forward and screen-up axes for the visual moon arc, with orbital phase mapped into a bounded forward-facing sweep.
- Preserve generated orbital data and simulation-clock progression; this is a presentation compromise pending a future physically grounded orbital/celestial coordinate model.
- User must confirm actual in-game visibility; automated tests do not establish visual success.

### 0.1.167 verification
- Implemented local-horizon constrained moon presentation and an above-horizon diagnostic.
- Pending installed-environment regression and visual confirmation; do not mark moon visibility resolved until confirmed in gameplay.


### 0.1.168 corrective focus — moon render path
- Calculate moon clearance against the camera-height-dependent geometric planetary horizon.
- Make moon discs independent of local lighting and render after terrain.
- Expose Babylon scene membership, active-mesh participation, material readiness, and camera-layer compatibility in diagnostics.
- Do not mark moon visibility complete until confirmed in the running browser.


## Moon visibility follow-through (0.1.169)
- Preserve the geometric horizon placement from 0.1.168.
- Bypass terrain depth testing for moon presentation meshes after the horizon-clearance constraint is applied.
- Confirm visible moon pixels in the installed browser before treating astronomical presentation as complete.


## Current diagnostic task — moon visibility

- [x] Add unmistakable per-moon diagnostic markers.
- [x] Show each moon's current apparent sky path.
- [x] Provide a programmatic switch to restore normal moon presentation.
- [ ] Confirm markers and paths visibly render in the user's browser.
- [ ] Use observed positions to correct the demonstrated placement/rendering failure.
- [ ] Add a player-facing option to toggle diagnostic paths/markers when the rendering issue is resolved.


## 0.1.193 — Persistent fractal terrain foundation

- Added per-world persistent terrain JSON assets with deterministic spherical fractal parameters and authored feature records.
- Added a separate persistent climate baseline.
- Integrated the saved terrain definition with the existing spherical surface sampler, mesh, movement, and camera-clearance paths.
- Next terrain stage: add a visual global elevation preview and developer editing tools that write to the same JSON definition.
- Later stages: climate-driven surface classification, moisture/temperature maps, erosion, rivers, and gameplay-driven geological events.
- Browser verification is required before treating terrain appearance and high-relief traversal as confirmed.


## 0.1.200 terrain follow-through

- Scale local terrain coverage to planet radius and maximum camera height so the patch extends beyond the expected planetary surface horizon.
- Use smooth multi-scale landforms with distinct amplitude bands for plains, hills, and mountain regions.
- Keep local terrain samples shared by rendered vertices, player movement, spawn placement, and altitude reporting.
- Validate the visible horizon and terrain silhouette in the installed browser; automated source assertions do not prove visual quality.


## 0.1.201 terrain relief and frame-time correction

- Corrected terrain seed hashing to hash the full WLD-/SYS-prefixed seed string; the previous hexadecimal-prefix parse collapsed these labeled seeds to zero and could make unrelated worlds share the same terrain.
- Reworked the player-centered relief field into three deterministic, smooth single-octave spatial scales (approximately 500 km, 200 km, and 85 km feature scales on an Earth-sized world) with stronger but bounded plains, hill, and mountain amplitudes. This is intended to restore visible elevation without returning to dense jagged peaks.
- The detailed local patch now has a small positive elevation datum and fades that datum to zero at its boundary. This keeps local valleys from falling behind the coarse planet sphere, which previously could hide the rendered patch while movement continued to sample the lower local elevation.
- Increased local patch resolution to 320 subdivisions while reducing the distant fallback sphere to 128 segments. Reduced repeated domain-warp work and reused regional/ridge samples during terrain classification.
- Terrain patch recentering now waits for 6 km of travel rather than resampling on very small movements, reducing avoidable synchronous CPU work.
- Moon orbital guide geometry is initialized once after orbital parameters are known rather than rebuilding its tube vertices on every render frame. Moon world positions, orbital phase, fixed planet-space axes, materials, and visibility rules are unchanged.
- Corrected stale QA expectations for the existing entity metadata, finite-safe local visual-root foot offset, and planet-fixed moon orbital frame. No celestial rendering baseline was changed.
- Browser/runtime verification is pending. In particular, confirm visible landform amplitude, that the player no longer sinks below the visible patch, and frame pacing during movement/animation.
