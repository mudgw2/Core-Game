# CORE — Project State

**Document purpose:** Authoritative continuity record for CORE Game 1.0.  
**Last updated:** 2026-11-21  
**Current release:** `0.1.200` (expanded horizon-spanning local terrain coverage, multi-scale landforms, and extended planetary camera far plane; browser verification pending)  
**Latest user-confirmed automated QA:** `0.1.165` — PASS, 163 tests (reported by user). Moon visibility remains unresolved through 0.1.165; 0.1.166 runtime verification is pending. **Latest assistant-run automated QA:** `0.1.193` — PASS, 179 tests; all 108 project PHP files linted and planetary environment JavaScript syntax passed. Browser terrain verification is pending. User previously confirmed planetary time and multiplayer longitude movement work in runtime.  
**Latest implementation source:** user-supplied complete CORE source archive `admin(6).zip` plus scoped 0.1.197–0.1.199 terrain/player patches and the 0.1.200 terrain coverage update.  
**Repository:** `mudgw2/Core-Game`

## 1. Project overview

CORE is a personal, single-player-first science-fiction sandbox inspired by EVE, Astrox Imperium, Starfinder 1E, and Traveller. It prioritizes stable, extensible backend/data architecture while progressively building a playable game. It is not intended to be an MMO.

Core goals include deterministic universe generation, character/universe assignment, star-by-star travel, detailed worlds, exploration/scanning, settlements, ships/crew, industry, piracy, bounty work, and persistent discoveries and changes.

## 2. Current verified baseline

CORE `0.1.154` is the latest user-confirmed stable baseline before the 0.1.155 candidate.

- User reports: **PASS — 162 tests passed**.
- User runtime confirmation: planetary clock works; multiplayer surface movement now changes longitude as well as latitude; user reports the movement issue is resolved.
- Assistant local regression run against the assembled 0.1.152 source: **145 passed, 0 failed**. This is a separate local result and must not replace the user's 162-test result.
- Assistant ran `php tests/run.php`; browser visual QA was not performed by the assistant. User did confirm runtime behavior for the clock and movement.

## 3. Latest release milestones

### 0.1.151 — Server-authoritative simulation clock and spherical movement
- Server-owned persistent simulation clock associated with universe/world.
- Authenticated clock synchronization and time adjustment API.
- Client synchronization/interpolation rather than browser-owned elapsed time.
- User subsequently confirmed the time behavior works.

### 0.1.153 — Development continuity and systems audit
- Added/updated `PROJECT_STATE.md`, `CORE-DESIGN-PRINCIPLES.md`, and `CORE-ROADMAP.md` as durable project continuity documents.
- Audited current world generation, astronomical presentation, character/gameplay, module infrastructure, and multiplayer foundations.

### 0.1.152 — Multiplayer planetary movement axis fix
- Client projects world-space movement onto local planetary east/north tangent directions.
- East/west maps to server strafe/X; north/south maps to server forward/Z.
- User confirmed longitude now changes correctly in the shared world and reports the full game test PASS — 162 tests.

### 0.1.155 — Visible world interaction improvements (candidate)
- Added spacebar jumping with a local spherical-surface normal arc and gravity-driven return. In shared worlds this is a client-side visual jump; server-authoritative jump replication is not yet implemented.
- Added a player-accessible World Environment briefing on `V`, displaying available world properties and clearly labelling missing/uncharacterized data.
- Strengthened starfield visibility with explicit alpha blending, a dedicated rendering group, night visibility control, and reduced night ambient fill. Runtime visibility still requires user browser verification.

### 0.1.154 — Deterministic physical world estimates and multi-moon orbits
- World profiles now include deterministic estimated physical properties: radius, mass, surface gravity, rotation period, and axial tilt. These are explicitly estimates, not measured astrophysical values.
- World profiles generate zero to three deterministic moon records (gas giants receive at least one), with stable IDs, approximate diameters, orbital radii, periods, inclinations, and phases.
- Astronomical presentation creates all supplied moons and advances their orbital positions from the server-authoritative simulation elapsed time. Visual orbital radii are compressed for readability.

### 0.1.166 — Camera-view-aligned lunar presentation arc
- Runtime diagnostics showed generated moon meshes and materials were healthy, but the active camera was pitched approximately 44 degrees below the local surface tangent. A horizon-anchored moon arc therefore fell outside the camera's vertical field of view.
- The visual lunar arc now uses the active camera's forward and screen-up axes, and constrains orbital-phase presentation to a compact forward-facing arc so moons remain in the current view.
- Generated moon records, orbital period, phase, inclination, radius, and authoritative simulation-clock progression remain unchanged. This is explicitly a camera-relative presentation model, not a physically accurate orbital path.
- Assistant local regression suite: 156 passed, 0 failed. JavaScript syntax and PHP syntax passed. Browser visual verification remains pending.

### 0.1.169 — Moon depth-test and render-order safeguard
- Keeps the existing geometric-horizon placement model.
- Forces opaque moon materials to bypass depth testing and render after terrain, because 0.1.168 diagnostics showed active, in-frustum moon meshes but no visible pixels.
- Adds diagnostics for material depth state, geometry presence, and active-camera identity.
- Browser visual verification remains pending.

### 0.1.168 — Geometric horizon and moon render-path visibility
- Moon altitude is checked against the actual tangent horizon of the planetary sphere at the camera altitude, rather than assuming the local tangent plane is the visible horizon.
- Moon materials are self-lit, independent of local terrain/night-side lighting, and render in a dedicated group after terrain.
- Diagnostics now report scene membership, active-mesh participation, material readiness, rendering group, layer-mask compatibility, and geometric-horizon clearance.
- Browser pixel-level visibility remains unverified; the new diagnostics are intended to identify whether Babylon actually submits the moons for rendering.

## 4. Current architecture inventory

### Universe and worlds
- PHP OOP/MVC with JSON/flat-file persistence.
- Deterministic `WorldGenerator` derives 1–9 UWP-style world profiles from universe/system seeds and stable system identity.
- `PlanetaryGeographyGenerator` creates deterministic world geography from world seed and UWP, including physical radius/diameter/circumference, a spherical-surface model descriptor, and a legacy axial-hex demonstration map.
- Geography includes water, plains, hills, mountains, deterministic population centers, and spaceport locations.
- Geography API persists the first generated baseline without overwriting established geography.
- This is an initial geography foundation, not yet a detailed multi-layer planetary simulation. Climate, geology, hydrology, biomes, resource deposits, and detailed global terrain remain future work.

### Astronomical environment
- `player/astronomical-environment.js` consumes star/system data and renders star descriptors, a deterministic background starfield, central-body presentation, and a planetary light/sky cycle.
- Legacy binary systems may receive a deterministic presentation companion without modifying saved universe records.
- World profiles now contain deterministic estimated physical properties and up to three generated moon records with orbital parameters. The renderer supports multiple moons and advances their positions using the authoritative simulation clock. This is a presentation-scale orbital model; it is not yet a full N-body or gravitational simulation.
- Persistent planetary time is server-authoritative through `api/world-clock.php` and `app/Simulation/SimulationClock.php`.
- Further work: coherent multi-star positioning/orbits and physical moon orbital behavior tied to generated system/world data.

### Planetary movement
- Babylon.js spherical surface environment in `player/planetary-environment.js`.
- Local/offline movement uses spherical tangent movement.
- Shared-world movement is server-authoritative through Colyseus and now converts client movement into local east/north components before transmission.
- User confirms longitude and latitude movement work after 0.1.152.
- Spacebar jumping is implemented as a client-side visual arc along the local surface normal. The authoritative multiplayer position remains on the surface; remote jump-state replication and collision-aware jump physics remain future work.

### Character and gameplay
- Character creation, data-driven attributes/species/roles, lifepath, inventory, crafting, fitting, scanning, discovery/investigation, and travel services exist.
- Character skill progression/framework is not yet established as a general gameplay system.
- Mining gameplay/module and persistent resource deposit depletion are not implemented.

### Modules
- PHP module registration/dependency framework exists (`ModuleInterface`, `ModuleRegistry`, `ModuleContext`, `ServiceContainer`).
- Existing default modules include runtime, universe, characters, data management, and content modules.
- The module framework is infrastructure; a player-facing optional Planet-Tech/gameplay module ecosystem with CORE Base fallbacks remains future design/implementation work.

### Multiplayer
- Colyseus 0.16 server foundation, authenticated universe rooms, token grant, remote player snapshots, and movement synchronization exist.
- Basic player connect/disconnect and copy-key controls exist in gameplay UI.
- More systematic developer-only multiplayer test controls are still needed.

## 5. Development priorities

Development follows the balanced incremental approach recorded in `CORE-DESIGN-PRINCIPLES.md` and `CORE-ROADMAP.md`.

Near-term priorities:
1. Continue richer world-level detail incrementally, beginning with geography/climate/geology data layers and generation/persistence boundaries.
2. Continue the astronomical environment: improve system-driven stellar positioning/lighting and validate moon presentation in the browser.
3. Add jumping using spherical gravity and local surface-normal physics; design shared-world jump authority before enabling it in multiplayer.
4. Return to character details and build a small, data-driven skill framework.
5. Build mining as the first skill/module proof of concept, connecting deposits, scanning, equipment, skills, inventory, and persistence.
6. Add developer-only multiplayer test controls and conduct more systematic multi-client testing.
7. Continue character/environment iteration, then use the user's Penpot files for a serious UI pass when supplied.

Technology Level advancement remains deliberately deferred, but TL is a cross-cutting future design constraint for worlds, species/roles, equipment, infrastructure, services, and capabilities.

## 6. Development and delivery rules

- Do not modify the live installation or push GitHub unless explicitly requested.
- Work from the latest verified source plus sequential patches; avoid repeated full-project uploads when the source is already available and synchronized.
- Deliver focused ZIP patches with project-relative paths.
- Exclude storage/player data, accounts, secrets, logs, backups, and generated universe/character state.
- Preserve backward compatibility and existing player data.
- Run real regression tests and syntax checks; report exact results and distinguish assistant-run checks from user-reported QA.
- Keep `PROJECT_STATE.md`, `CORE-DESIGN-PRINCIPLES.md`, and `CORE-ROADMAP.md` current as part of development releases.
- Do not implement changes merely because a bug or idea is discussed; wait for explicit authorization. The current user authorization is for this next incremental development update.

## 7. Environment and workflow

User develops locally on a Proxmox-hosted Apache environment with a Samba development share and VS Code. GitHub repository `mudgw2/Core-Game` is primarily backup/source control. Prefer local/server-side development and free/open-source/self-hosted tooling.


## CORE 0.1.156 — Visible Character and Planetary Presentation

- Stable release candidate: 0.1.156 (pending user runtime verification).
- Connected the humanoid GLB `Jump_Start`, `Jump_air`, and `Jump_Land` clips to the existing spherical-gravity jump arc. Physics and animation are separate states; missing clips fall back without disabling jump physics.
- Reworked the night starfield into 720 larger camera-facing emissive sprites with explicit additive blending and night visibility.
- Increased deterministic terrain elevation/depth relief and separated terrain colors to make planetary surface variation more legible.
- User runtime confirmation is pending. Automated QA result is recorded in `qa/QA-RESULTS-0.1.156.txt`.


## CORE 0.1.157 — Animation Blending and Screen-Space Night Sky

- Character animation groups now enable Babylon.js pose blending (`enableBlending`, `blendingSpeed`) for locomotion and jump transitions. The existing takeoff, airborne, and landing clips remain connected to spherical-gravity jump physics.
- Replaced the unverified 3D star sprites with a deterministic transparent `DynamicTexture` rendered by a dedicated background `BABYLON.Layer`. This removes dependence on world-space star placement, camera clipping, billboard orientation, and scene depth ordering.
- Star layer opacity follows the existing calculated night visibility; the layer and texture are disposed with the astronomical environment.
- Added regression assertions for the screen-space star layer, deterministic texture population/disposal, and animation blending.
- Automated checks are recorded in `qa/QA-RESULTS-0.1.157.txt`. Browser/runtime visual verification is still required; do not treat the stars or animation quality as user-confirmed until tested in-game.


## CORE 0.1.158 — Jump Animation Playback Regression Correction

- Removed the per-AnimationGroup blending settings introduced in 0.1.157 after user runtime testing showed that locomotion and jump animations were broken.
- Restored the previously established Babylon.js AnimationGroup stop/start playback path for locomotion and one-shot jump clips.
- Preserved the 0.1.157 screen-space deterministic night-sky implementation.
- Jump physics, takeoff/airborne/landing clip selection, and the existing completion callbacks remain separate and unchanged.
- This is a corrective playback restoration, not a claim that transitions now crossfade smoothly. Browser verification is required; specifically test idle, walk, run, takeoff, airborne, landing, and return to locomotion.
- User-reported 0.1.157 automated QA: 162 passed. Assistant-run 0.1.158 QA is recorded separately in `qa/QA-RESULTS-0.1.158.txt`.


## 0.1.158 — Jump animation playback correction

- Removed the per-AnimationGroup blending settings introduced in 0.1.157 after user runtime testing reported broken locomotion and jump animations.
- Restored the established Babylon.js AnimationGroup stop/start playback path while preserving jump physics and takeoff/airborne/landing clip selection.
- Assistant-run tests: 152 passed, 0 failed. User runtime confirmation is not present in the current project record.

## 0.1.159 — Moon visibility and 1337 System 01 test coverage

- Fixed an undefined `moonAngle` reference in the per-frame moon update; this could throw and abort astronomical rendering on worlds with moons. Moon mesh rotation now uses the defined `phaseAngle`.
- Reframed moon positions into a stable local sky coordinate frame derived from the entry camera heading and local surface normal. This addresses the previous global X/Z orbit plane, which could place moons behind terrain or outside the default view.
- Increased moon visual size/readability and widened the planetary camera field of view for a near-horizon moon arc. The sky frame remains fixed after initialization so camera panning can move away from and back toward the moons.
- Universe 1337 / Constellation 01 / System 01 retains its original moon data for Worlds 01-1, 01-2, 01-4, and 01-5. Deterministic test moons are added only to previously moonless Worlds 01-3 and 01-6. The override is scoped to the exact universe seed and system ID and does not alter saved universe records.
- Assistant-run tests: 153 passed, 0 failed. JavaScript/PHP syntax checks pass. Browser visual/runtime QA was not performed; user verification is required.
- The user-reported 162-test PASS applies to 0.1.157 and must remain distinct from assistant-run test totals for later patches.


## 0.1.160 — Jump phase reliability and planetary camera inspection

- Fixed multiplayer idle animation selection so stationary server-controlled movement no longer replaces an active takeoff/airborne/landing animation every frame.
- Changed the takeoff-to-airborne handoff to occur at the physical jump apex. This prevents a long authored takeoff clip from delaying or suppressing the airborne clip; jump physics remains authoritative.
- Expanded planetary ArcRotateCamera pitch limits so mouse drag can look above the horizon and toward the ground.
- Added an `F` camera toggle between the normal third-person framing and a close over-the-shoulder view. Mouse-wheel zoom remains available. Terrain clearance remains enforced with a lower safe camera-height threshold for close viewing.
- Raised the deterministic moon presentation arc from a very low near-horizon band to a more discoverable altitude while retaining each moon's phase, period, and inclination-driven variation. This is a presentation adjustment, not a change to physical orbital data.
- Preserved the 1337 / Constellation 01 / System 01 deterministic test moons and the 0.1.157 screen-space star layer.
- Automated tests and syntax checks are recorded in `qa/QA-RESULTS-0.1.160.txt`. Browser visual/runtime QA has not been performed; user verification is required for animation quality, camera feel, moon visibility, and Constellation view stability.


## 0.1.161 — first-person camera and moon visibility correction

- Add a distinct first-person camera mode. Mouse-wheel zoom can enter first-person automatically; `F` cycles third-person → close → first-person → third-person. The player model is hidden while the camera is at eye height, and terrain-clearance clamping is bypassed only in this mode.
- Restore the close/third-person terrain safety behavior when leaving first-person. Scrolling outward exits first-person before the camera can pass through the surface.
- Reframe moon presentation to a low local-horizon arc because the previous 0.1.160 altitude placed moons above the default downward-looking camera frustum. Increase visual diameter/emissive readability, explicitly keep moon meshes active/visible, and extend the camera far plane. Orbital phase and period remain data-driven.
- Automated assistant QA: `php tests/run.php` — **154 passed, 0 failed**; JavaScript syntax checks passed for both changed player scripts; PHP syntax checks passed.
- Browser visual/runtime QA has not been performed. User verification is required for first-person entry/exit, mouse look, moon visibility on World 01-1 at night, and continued jump behavior.


## CORE 0.1.164 — browser shortcut preservation

- Corrected the gameplay `keydown` handler so Ctrl-, Command/Meta-, and Alt-modified key events are ignored by game shortcuts. This prevents Ctrl+C from being treated as the unmodified `C` character-information shortcut, and protects other browser/OS shortcuts from gameplay keybindings.
- Existing editable-target exclusions remain in place for contenteditable elements, inputs, textareas, and selects.
- This release does not change moon rendering or diagnostic content; it fixes the keyboard conflict encountered while trying to copy diagnostic information.
- Assistant-run regression and syntax results are recorded in `qa/QA-RESULTS-0.1.164.txt`. Browser runtime verification is pending.


## CORE 0.1.165 — active-camera moon presentation frame

- Replaced the one-time frozen moon sky basis with a local tangent frame rebuilt from the active camera direction on each astronomical-environment update.
- The prior basis was initialized from the first post-spawn camera heading and then remained fixed. The supplied runtime diagnostics showed the camera looking almost directly along negative Z while the moon sat almost directly along positive Z, behind the camera.
- Moon orbit phase, period, inclination, radius, generated world data, mesh/material properties, and simulation clock remain unchanged. This is a presentation-coordinate correction only.
- Added CLI and browser QA source-regression checks ensuring moon placement uses the current camera-forward tangent and does not retain a frozen `this.moonSkyBasis`.
- Assistant verification is recorded in `qa/QA-RESULTS-0.1.165.txt`. Browser visual runtime verification remains pending; user must confirm whether the moon is now visible.
## Documentation layout

- All `INSTALL-*.txt` instructions are stored in `install/`.
- Update manifests, patch notes, and deletion manifests are stored in `update/`.
- Hotfix notes are stored in `hotfix/`.
- QA reports remain in `qa/`; release notes remain in `release/`; historical changelog entries remain in `changelog/`.
- Keep the project root free of release/QA/update/hotfix instruction files.

### 0.1.167 — Planetary-horizon constrained lunar presentation
- Replaced the camera screen-plane moon frame with a local planetary sky frame.
- Camera heading is projected onto the local surface tangent plane; moon elevation is measured along the outward surface normal.
- Lunar presentation now maintains a positive local-horizon altitude, avoiding placement through the planet when the camera pitches downward.
- Added per-moon `aboveLocalHorizon` runtime diagnostic.
- Generated moon records and simulation-clock-driven phase progression remain unchanged. Visual path remains a presentation arc, not a physically accurate orbit.
- Automated and browser/runtime verification are pending user testing.


## 0.1.170 — Moon diagnostic visualization

Diagnostic moon markers (cyan/orange) and apparent orbital-path tubes are enabled by default. `setMoonDebugVisuals(false)` disables the diagnostic presentation and restores normal moon materials for a future settings control. This release is for visual diagnosis; actual browser visibility remains unverified.


## 0.1.178 — Persistent diagnostics subsystem
- Added opt-in browser diagnostic collection and JSON export/submission from both gameplay navigation and the full-screen Planetary Test World.
- Browser capture includes JS errors/rejections/console errors, WebGL context events, connectivity/visibility changes, browser/canvas/WebGL details, and snapshots of the primary Babylon scenes and cameras.
- Added authenticated, CSRF-protected report intake at `api/diagnostics-report.php`; reports are size-limited, throttled, stored under protected `storage/diagnostics/`, and retained to 100 files.
- Existing PHP warning/exception/fatal capture remains; persistent log files now rotate at 5 MB with three generations retained. Log entries include CORE version, request ID, request method/path, and a non-reversible session fingerprint.
- Automated source checks and PHP/JS syntax checks are required before packaging. Actual Apache write permissions and browser-to-server report submission remain runtime checks for the user.


## CORE 0.1.179 — Universe navigation and celestial visibility

- Reworked the player map hierarchy so the initial Universe View displays all Constellations, with labeled 3D constellation markers, system counts, and inter-constellation links where navigation data supports them. Selecting a constellation opens its Constellation View; the Back to Universe View control is visible only in that child view.
- Added a contextual Universe View action and a Constellation list; the main action changes to View Selected System in Constellation View.
- Enlarged planetary moon meshes, separated apparent sky lanes, brightened debug materials, and increased orbit-tube thickness so both moons and their paths are intentionally conspicuous. These are visual presentation adjustments; generated orbital records and authoritative simulation timing are unchanged.
- Kept a faint screen-space starfield visible outside full night conditions; terrain continues to occlude sky objects below the planetary horizon. Diagnostics now include star-layer state, moon scale, and screen-space moon separation.
- Repositioned the planetary diagnostics control to a collapsed bottom-left panel, away from the existing bottom-right connection/status HUD.
- Added hover identification for System View planets and moons, including the parent world name/ID for moons.
- Retained the 0.1.178 persistent server/browser diagnostics subsystem and the permanent Constellation-grid removal.
- Assistant-run QA: `php tests/run.php` — 169 passed, 0 failed. PHP syntax checks passed for changed PHP files; Node syntax checks passed for changed JavaScript files. Browser rendering/visual QA has not been performed and remains user-verifiable.


## 0.1.180 — Hierarchy labels and planetary visibility correction
- Map billboard textures now explicitly flip their V scale so Universe and Constellation labels render upright.
- Constellation View now displays each star system name and world count, with larger markers and expanded framing.
- Universe constellation labels use separated offsets and a wider coordinate scale; the redundant Back to Universe View button and handler are removed.
- System View displays persistent labels above planets (name + UWP) and moons (name + parent world), in addition to hover identification.
- Planetary moons are presented closer to the camera with larger apparent scale; moon orbit paths are thicker and brighter, and the visible lanes are separated. This is still a camera-relative presentation model.
- Browser/runtime visual verification remains pending. The dark sky obstruction reported by the user is not declared fixed without runtime evidence.


### 0.1.181 — White moon visibility proof
- Changed the planetary moon debug material to pure white, strongly emissive, unlit surfaces.
- Placed 3D moon meshes and orbit tubes in the final rendering group.
- Added a screen-space overlay above `worldCanvas`, projected from each live moon mesh through the active camera. It draws large white circles, dark outlines, `MOON 1` / `MOON 2` labels, and white orbital traces. This overlay is independent of Babylon scene depth and mesh occlusion.
- Added `moonProofOverlay.visibleMoonCount` diagnostics. A zero count means the moon positions are not projecting into the active viewport; a positive count with no visible overlay would indicate a DOM/CSS layering issue.
- The overlay is disposed with the astronomical environment. Generated moon data and simulation-clock progression are unchanged.
- Assistant QA: 170 passed, 0 failed; PHP and JavaScript syntax checks passed. Browser visual confirmation remains pending. The white proof overlay is intentionally temporary and is not the final moon art.


## CORE 0.1.182 — Planet-fixed 3D moon correction
- Replaced camera-relative moon placement with a fixed planet-space orbital frame defined by the planet's fixed world axes. The live camera no longer participates in calculating moon world positions.
- Actual moon meshes are positioned around the planet origin using generated orbital radius and inclination; full 3D orbit tubes use the same basis and radius.
- Moon orbital phase continues to advance from simulation elapsed time and each moon's orbital period.
- Removed the screen-space SVG proof overlay. Bright white emissive 3D moon meshes and depth-independent 3D orbit tubes remain enabled for development visibility.
- Diagnostics expose `worldAnchored`, `cameraRelative`, `orbitalRadiusWorld`, `orbitCenter`, and `orbitPlaneNormal`.
- Browser acceptance is pending. User must verify that camera turns move the moons naturally across the view, and simulation time advances the moons along their visible 3D paths. Depth bypass may show far-side moons through the planet during this temporary development phase.


## CORE 0.1.193 — Persistent Fractal Planetary Terrain

- Each world now has a persistent editable terrain definition in `storage/data/world_terrain/<world-id>.json`. It is created atomically when missing and never regenerated over an existing valid definition.
- Terrain definitions store a deterministic terrain seed, generator version, hydrographics target, elevation scale, continental/regional/mountain/detail frequencies, domain-warp strength, and authored modifications rather than a giant baked heightmap.
- The planetary renderer consumes the persisted parameters and uses spherical domain-warped fractal noise for continental shapes, regional hills, ridged mountains, and fine detail.
- The modification schema supports mountain features, volcanoes, meteor craters, and elevation stamps. A meteor shower can be represented as multiple crater records.
- Climate is persisted independently in `storage/data/world_climate/<world-id>.json`; temperature/moisture parameters are not conflated with elevation. Elevation lapse rate is represented as a climate input.
- Existing geography and spherical movement interfaces remain in place. Terrain sampling continues to feed player ground positioning and camera clearance. Browser verification remains pending.
- A graphical elevation preview/editor is explicitly deferred to the next terrain-development stage; JSON remains the authoritative asset and can be edited directly in the meantime.


## CORE 0.1.200 — Horizon-spanning terrain and multi-scale landforms

- Made player-centered terrain patch coverage scale with planet radius and the maximum third-person camera height (24 km), with an additional 100 km margin; typical worlds receive roughly 600–1,500 km of square terrain coverage. This addresses the straight, nearby terrain boundary seen in the 0.1.199 runtime screenshot.
- Set patch resolution to 256 subdivisions; world-size-dependent spacing remains suitable for the broad landform scales.
- Replaced the overly subdued two-scale local relief with three smooth scales: broad regional rises, rolling mid-scale terrain, and restrained fine breakup. Plains retain lower amplitude; hills and mountains receive progressively stronger relief.
- Kept the persisted global elevation exaggeration bounded at 2.75×; close-range landform readability comes from the multi-scale relief field rather than globally amplifying every elevation category.
- Extended Babylon's camera far plane dynamically beyond the planet radius. The patch boundary, not the old camera far plane, was the likely cause of the visible straight horizon; browser confirmation remains pending.
- Updated terrain QA assertions and the centralized release version.
- Local automated test suite and syntax checks must be recorded separately from user browser verification.


## CORE 0.1.201 — terrain relief, patch visibility, and frame pacing

- Current release candidate: 0.1.201. Built by applying the supplied incremental 0.1.197–0.1.200 patches to the complete `admin(6).zip` source tree.
- Terrain seed initialization now hashes the full persisted seed string; labeled `WLD-`/`SYS-` seeds no longer collapse to zero via hexadecimal-prefix parsing.
- Terrain local relief uses broader deterministic spatial scales and stronger class-based amplitudes; the near patch is 320 subdivisions and the coarse planet sphere is 128 segments.
- Important patch/sphere correction: local relief receives a small positive offset that fades to zero at the patch edge. This prevents negative local patch elevations from being hidden behind the coarse sphere while movement still samples them.
- Terrain noise work was reduced by using single-octave lattice samples for the local landform/warp fields, sharing regional/ridge samples, and recentering the patch only after 6 km of movement.
- Static planetary moon orbit tubes are updated once after their orbital plane/radius are available, not rebuilt each frame. Celestial positioning and the accepted 0.1.190 fog-isolation behavior remain unchanged.
- The 0.1.200 user QA reported 162 PASS / 3 FAIL. Two failures were stale source assertions (entity metadata marker and finite-safe foot offset); the moon failure expected an obsolete camera-relative sky basis even though the current source uses fixed planet-space orbital axes. QA checks were updated to match the actual current architecture.
- Assistant-run verification for 0.1.201 must be recorded in `qa/QA-RESULTS-0.1.201.txt`. Browser visual/performance confirmation remains pending and must come from the user runtime test.
