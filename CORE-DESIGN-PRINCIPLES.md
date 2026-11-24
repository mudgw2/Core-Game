# CORE — Design Principles

**Status:** Active project decisions  
**Last updated:** 2026-11-21  
**Latest user-reported regression baseline:** 0.1.160 (162 tests PASS; jump animation runtime-confirmed). 0.1.161 camera and moon changes await runtime verification.

This document records durable design decisions. It is intended to guide implementation without forcing premature implementation of future systems.

## 1. Balanced incremental development

Develop all major game systems in small, coordinated increments. Avoid allowing one subsystem to become highly advanced while other foundational systems become difficult to integrate or recover. Prefer small, testable changes that keep the whole game progressively playable. Not every release must touch every area, but the roadmap should rotate attention across world, environment, character, gameplay, multiplayer, and interface.

## 2. Procedural generation and persistence

- Prefer deterministic, seed/formula-based generation for repeatable environmental content rather than storing large collections of generated assets.
- Use stable identities and explicit generator/schema versions.
- Separate generated baseline from player-authored or gameplay-persistent changes.
- Persist seeds, parameters, generator versions, and meaningful exceptions/changes; reconstruct deterministic baseline where possible.
- Do not regenerate over established persistent player changes.

## 3. World hierarchy and world detail

- Preserve the hierarchy Universe → Constellation → Star System → World.
- World profiles are derived deterministically from universe/system/world identity and should evolve toward richer physical, geographic, atmospheric, climatic, geological, hydrological, ecological, settlement, and resource layers. Current physical values are explicitly labelled deterministic estimates, not authoritative astrophysical measurements.
- Keep these layers conceptually distinct but geographically consistent and interoperable.
- Distinguish generated world facts from what a character has discovered; scanning and exploration should reveal knowledge progressively.
- Traveller/UWP concepts are useful references, not a required external data format.

### Persistent planetary terrain layers (0.1.193)
- Each world has a separate, persistent terrain JSON asset containing a stable terrain seed, generator/schema version, fractal parameters, and authored modifications; do not store a giant generated heightmap as the default source of truth.
- Generate the terrain asset atomically only when missing. Never overwrite an existing valid terrain definition when generator code changes.
- Keep generated terrain, developer-authored modifications, and later gameplay/simulation changes logically distinct.
- Keep climate as a separate persistent layer. Elevation may influence temperature and other climate calculations, but climate maps must not be used as the primary elevation generator.
- A visual elevation preview/editor should edit the same authoritative JSON asset rather than creating a competing terrain data source.

## 4. Astronomical and planetary simulation

- The visible sky should be driven by the actual generated star-system data, not merely decorative background elements.
- Natural satellites are represented by deterministic world data including stable identity, approximate diameter, orbital radius, period, inclination, and phase. The renderer should support multiple moons and derive their positions from the authoritative simulation clock. Orbital distances may be visually compressed for readability, but their data-driven period/phase relationships should remain coherent.
- Planetary time is server-authoritative and persistent per universe/world; clients interpolate and synchronize rather than owning simulation time.
- Surface movement and orientation must respect spherical geometry and local tangent directions.
- Future jumping must follow local surface-normal/gravity behavior rather than a flat-world vertical axis.

## 5. Character and gameplay systems

- Character data, skills, equipment, world resources, and gameplay modules should interact through clear contracts rather than tightly embedding one feature inside another.
- Skills should be data-driven where practical and support definitions, levels, progression, checks, requirements, and effects.
- Mining is intended as an early proof of the skill/module architecture: generated deposits, discovery/scanning, equipment capability, skill-influenced extraction, inventory rewards, and persistent deposit depletion.
- Do not introduce a large skill catalogue before a small set proves the framework.

## 6. Modules and fallback behavior

- CORE Base provides essential runtime behavior and simple fallback representations.
- Optional modules may provide richer definitions, algorithms, content, or presentation (for example geology, vegetation, hydrology, structures, and mining).
- Missing or disabled optional modules must not make core worlds unplayable; use safe base behavior or fallback content.
- Module dependencies and registration should remain explicit and validated.

## 7. Technology Level

Technology Level is a cross-cutting concept that will eventually influence worlds, infrastructure, equipment, capabilities, services, species, classes/roles, and other relevant entities. Universe TL establishes a starting context, with initial child-level caps that are not necessarily permanent. TL advancement is deliberately deferred; do not implement it prematurely, but avoid architecture that makes future TL integration difficult.

## 8. Multiplayer and authority

- Shared-world state that must agree across clients is server-authoritative.
- Client movement is expressed in a well-defined local planetary coordinate frame and validated/bounded by the server.
- Test tools must be developer-only and must not bypass normal account ownership, authorization, or persistence safeguards.
- Multiplayer testing should cover synchronization, reconnection, movement, time, and state consistency.

## 9. Presentation and interface

- Babylon.js is the intended 3D engine; do not introduce Three.js.
- Initial world art direction is low-poly, using simple geometry, faceted silhouettes, restrained colors, and minimal textures.
- Keep gameplay systems and data contracts ahead of presentation polish, but iterate UI alongside the rest of the game to prevent it becoming disconnected from actual functionality.
- Penpot designs supplied by the user should guide a later deliberate UI implementation pass.

## 10. Compatibility and delivery

- Preserve existing player data, universes, accounts, secrets, and storage during code updates.
- Use focused incremental patches and real regression tests.
- Keep project Markdown continuity documents current as implementation, test results, architecture decisions, and priorities change.
- Repository documentation is the durable source of truth; chat memory is supplementary.


## Visible presentation is part of feature completion

A generated property or scene object is not considered delivered gameplay merely because it exists in data or source code. Player-facing features must be visually legible and runtime-verifiable. Character animation remains separate from movement physics; procedural environmental detail must remain deterministic from world seeds.


## Rendering reliability — 0.1.157

- Background phenomena that should be independent of world geometry, camera distance, and terrain occlusion should use a screen-space/background rendering path rather than distant world-space meshes when that materially improves reliability.
- Environmental presentation must remain deterministic from the universe/system seed, and visual layers must be disposed with their owning scene/environment.
- Character animation transitions should blend from the currently displayed pose where supported, while gameplay physics remains authoritative and independent of animation clip timing.


## Animation playback reliability — 0.1.158

- Keep imported GLB AnimationGroup playback on the established Babylon.js start/stop path unless a transition method is verified in the running game.
- Do not enable per-group blending solely on the basis of static API assumptions; test all locomotion and jump clips in-browser before adopting a new transition mechanism.
- Jump physics remains independent of animation playback and must continue to work if an animation clip is absent or fails.


## Celestial presentation reliability — 0.1.159

- Surface-view celestial objects must be positioned relative to a stable local sky frame and the player's planetary surface orientation, not assumed global axes that may place them behind terrain or outside the initial camera view.
- Preserve deterministic orbital data while allowing a presentation-scale sky arc that remains discoverable from the playable camera framing. Clearly distinguish compressed visual presentation from physical orbital simulation.
- Test fixtures may add deterministic content only when tightly scoped to a named seed and stable entity ID; normal generation for all other universes and systems must remain unchanged.
- Generated celestial data is not considered delivered until the corresponding object is visible and testable in the running game.


## Jump and planetary camera reliability — 0.1.160

- Animation phase changes must follow gameplay state (takeoff, apex/airborne, landing, grounded), not depend solely on authored clip duration. Movement physics remains authoritative and animation playback must not be overwritten by idle locomotion while airborne.
- Camera pitch and zoom are player controls. Planetary surface safety constraints must prevent terrain penetration without making the sky inaccessible or preventing a useful close-view mode.
- Celestial objects must be framed at altitudes that can be discovered from the normal playable camera; an object technically above the geometric horizon but hidden below the default view is not a successful player-facing feature.


## First-person camera and sky framing — 0.1.161

- The planetary camera supports distinct third-person, close, and first-person modes. First-person is centered at character eye height, hides the local character model to avoid head/body clipping, and must not be forced back outside the terrain by third-person clearance constraints.
- Wheel zoom is allowed to transition into first-person; zooming outward restores a safe third-person camera. Explicit mode cycling remains available through the `F` key.
- Astronomical objects must be framed for the actual default camera view, not merely placed above the local horizon in abstract world coordinates. Moon presentation must remain legible at night while preserving deterministic, clock-driven phase/orbital data.


## Runtime diagnosis before visual guesswork
When a rendered object is persistently absent, inspect the live scene state (data count, mesh creation, enabled/visible state, material, world position, camera distance, and projected screen position) before changing visual scale, brightness, or placement. Prefer measurable diagnostics over speculative rendering adjustments.


## Browser shortcut compatibility — 0.1.164

- Gameplay keybindings must only handle unmodified keys. Ignore Ctrl, Command/Meta, and Alt modified key events so browser, operating-system, and standard copy/paste shortcuts retain their native behavior.
- Continue to exclude editable controls and contenteditable targets from gameplay shortcuts.


## Camera-relative moon presentation — 0.1.165

- Derive the moon presentation tangent frame from the active camera direction on every update, projected onto the local planetary tangent plane.
- Do not freeze the presentation frame from the initial camera heading: subsequent camera reorientation can otherwise place the moon behind the viewer.
- Keep this as a rendering/presentation transform. Preserve generated moon records, deterministic orbital parameters, and simulation-clock authority.


## Astronomical presentation clarification (0.1.166)

The current surface-game moon display is a camera-relative presentation arc designed to keep generated moons discoverable in the active viewport. Generated phase, period, inclination, and radius remain data-driven, but the displayed path is not a physically accurate orbit. A future astronomy pass may replace this compromise with a world-fixed orbital frame and camera-independent celestial geometry once the full planetary/camera coordinate model is validated.

### Astronomical presentation constraint (0.1.167)
Moon presentation coordinates must respect the local planetary surface normal. Camera orientation may determine the azimuthal heading, but must not cause moon positions to point through the planet. Runtime visibility diagnostics should distinguish viewport projection from being above the local horizon.


## Astronomical render verification (0.1.168)
The visible horizon is the geometric tangent to the planetary sphere from the camera position, not simply the local tangent plane. Moon diagnostics must separately verify geometric horizon clearance, Babylon scene membership, active-mesh selection, material readiness, and camera layer compatibility. Moon presentation materials must remain visible independently of local terrain lighting.


## Moon visibility acceptance rule (0.1.169)
Moon presentation positions must clear the geometric planetary horizon and remain outside the planetary surface. When those conditions are met, rendering must not allow terrain depth-buffer artifacts to suppress the moon discs. Moon materials are opaque, self-lit, and rendered after terrain; visual runtime confirmation remains mandatory.


## Astronomical rendering diagnostics

Temporary moon diagnostics may render conspicuous markers and apparent sky-path guides without modifying authoritative generated moon records or orbital parameters. Diagnostic presentation must be independently switchable from normal moon appearance. Apparent sky paths must not be described as physical orbital trajectories.


## Diagnostic celestial visibility proof — 0.1.181
When a celestial mesh remains invisible despite being present and active in the Babylon scene, provide a temporary screen-space proof representation projected from the live mesh position. Use unmistakable high-contrast markers, labels, and orbital traces that are independent of scene depth and mesh occlusion. Report the number of objects projected into the active viewport. This is a diagnostic aid, not a substitute for correcting the underlying 3D render path or delivering the final naturalistic appearance.


## Planet-fixed planetary moons — 0.1.182

- Planetary moons are actual 3D celestial bodies anchored to the parent planet's world-space origin. Their orbital frame is fixed in planet coordinates and must never be rebuilt from the live camera direction or camera position.
- Camera rotation changes only the camera projection of moon positions; it must not move the moons in world space.
- Moon meshes and their complete orbital tubes share the same planet-fixed orbital basis, radius, inclination, and simulation-clock-driven phase.
- Bright white, emissive, depth-independent development rendering remains enabled until the user confirms that the actual 3D moons and their orbits are visible and behave correctly. Screen-space moon substitutes are not acceptable as proof of 3D behavior.
- Development depth bypass may reveal far-side moons through the planet. This is a deliberate temporary visibility aid and must be distinguished from final naturalistic occlusion.


## Terrain patch/base-surface agreement

- A high-resolution player-centered terrain patch must never be hidden below the coarse planet fallback mesh while movement samples the patch's lower elevation. Local relief therefore uses a positive datum that fades to zero at the patch boundary, keeping the two surfaces visually and mathematically consistent.
- The local patch is the close-range relief source; the planet-wide sphere is a lightweight distant fallback. Avoid excessive global sphere tessellation when it does not contribute visible close-range detail.
- Expensive CPU terrain resampling and static orbital guide geometry updates must not run at render-frame frequency unless their source state actually changes.
