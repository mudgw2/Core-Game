# CORE — Project State

**Document purpose:** Authoritative handoff and continuity record for the CORE game project.
**Last updated:** 2026-11-10
**Last user-verified baseline:** `0.1.85`
**Latest prepared release:** `0.1.86` (user installation QA pending)
**Repository:** `mudgw2/Core-Game` (private GitHub repository)

---

## 1. Project overview

CORE is a personal, single-player science-fiction sandbox inspired by EVE, Astrox Imperium, Starfinder 1E, and Traveller.

The project prioritizes a stable, extensible backend and persistent data model before expanding gameplay and presentation. It is not intended to be an MMO. A shared universe seed should allow different users or installations to generate the same procedural baseline, while each player's persistent state remains independent.

### Core design goals

- Procedural universe generation driven by a user-provided seed.
- Characters must belong to a universe before they can enter gameplay.
- Character creation and universe creation remain separate workflows.
- Traveller/UWP-like world and system data.
- Star-by-star travel, scanning, exploration, and persistent discoveries.
- Starting locations such as a world, city, station, or outpost.
- Room for ship, officer, and crew management.
- Multiple gameplay paths, including industry, piracy, bounty work, and exploration.
- Data-driven game values and definitions, primarily under `data/`.

---

## 2. Current version and QA status

### Previous verified baseline: CORE 0.1.84

The `0.1.84` character-to-universe assignment patch was delivered as a ZIP patch.

The user subsequently ran the browser QA suite and reported:

> PASS — 130 tests passed.

This is the accepted user-verified QA result for the `0.1.84` working baseline.

The patch delivery also reported the following checks:

| Check | Reported result |
|---|---:|
| Browser QA | 130 passed, 0 failed |
| CLI QA | 73 passed, 0 failed |
| PHP syntax | 84 files clean |
| JavaScript syntax | 4 files clean |

The browser QA result is explicitly confirmed by the user. The other results are recorded as reported during patch delivery, not independently re-run against the user's installed copy.


### Current release: CORE 0.1.85 — Account authorization

The `0.1.85` patch builds on the `0.1.84` character/universe assignment baseline. It adds centralized session authorization for player-facing gameplay APIs:

- A shared `PlayerApiSecurity` guard protects character records, character collections, gameplay mutations, navigation, and universe lifecycle operations.
- Character reads and mutations require the signed-in account to own the character.
- Universe navigation and lifecycle operations require the account to own at least one character assigned to that universe.
- Player API writes require the session token in the `X-CORE-CSRF` header. The gameplay client now sends it automatically.
- Player character-creation API calls persist the signed-in account as `owner_account_id`; creating directly inside an existing universe also requires an owned character in that universe. Character collections are filtered to the signed-in account.
- The trusted development/private-network Workbench retains its existing administrative access and does not require player login or API CSRF headers.
- Permanent character deletion remains unavailable to player sessions and stays restricted to the trusted Workbench route.

**0.1.85 validation:** CLI suite 76 passed / 0 failed; browser QA 136 passed / 0 failed; PHP syntax checks passed across the project; JavaScript syntax checks passed.

### Current prepared release: CORE 0.1.86 — Character ability generation

The `0.1.86` patch completes the ability-generation step that was previously called out as unfinished in the Character Creation screen.

- Adds a data-driven 10-point ability point-buy configuration in `data/character-creation-rules.json`.
- Base scores are purchased from 7 through 16 before species adjustments.
- Configured point costs include point refunds for scores below 10.
- Human receives +2 to one selected ability; Android receives +2 Dexterity, +2 Intelligence, −2 Charisma; Ysoki receives +2 Dexterity, +2 Intelligence, −2 Strength.
- Character Creation displays points remaining, point costs, final adjusted scores, and a species-adjustment summary.
- The server recalculates and validates the allocation and species modifiers. The browser preview is not trusted for validation.
- Character records preserve `base_attributes`, final `attributes`, `species_adjustments`, `ability_points_spent`, `ability_point_budget`, and `human_bonus_attribute`. Existing gameplay code continues to read final scores from `attributes`.
- The existing editable `storage/data/content/character-options.json` catalog is not modified. Generation rules are stored separately to avoid replacing user-managed content.

**0.1.86 assistant-run validation:** CLI suite 78 passed / 0 failed; browser QA endpoint 138 passed / 0 failed (HTTP 200); PHP syntax 86 files clean; JavaScript syntax 5 files clean. User installation verification is pending. Do not call 0.1.86 the accepted baseline until the user reports their QA result.

### Important repository discrepancy

The connected GitHub repository's `main` branch was checked on 2026-11-09. Its `config/version.php` still returned `0.1.83`.

Therefore:

- `0.1.84` was the accepted working/QA baseline before the `0.1.85` authorization patch.
- `0.1.85` is the last user-verified baseline; the user reported PASS — 136 tests passed.
- `0.1.86` is the latest prepared patch; user installation QA is pending.
- GitHub `main` was observed to be behind at `0.1.83`.
- Do not assume GitHub `main` contains the `0.1.84` or `0.1.85` patches.
- Do not use the stale `main` branch as the source for a new patch until it is synchronized or otherwise verified.
- Do not ask the user to re-upload source that is already available in the active project context. First inspect available project files and repository state.

---

## 3. Technology and architecture

### Current implementation direction

- PHP, using an MVC/OOP structure.
- JavaScript for client-side behavior.
- Bootstrap, with project-specific CSS.
- JSON and flat-file persistence for the initial implementation.
- No MySQL dependency at this stage.
- REST-style API endpoints as the application develops.
- Babylon.js is the intended interactive 3D engine.
- Do not introduce Three.js.
- Begin with low-poly primitive geometry; GLB assets can be introduced later.

### Persistence and data integrity principles

The architecture is intended to support:

- Stable record IDs that are separate from user-facing seeds.
- Generated baseline data separated from persistent player state.
- Per-record locking for concurrent writes.
- State revision tracking.
- Event/history records where appropriate.
- Safe, atomic persistence of JSON/flat-file records.
- Data-driven definitions and balance values.

Preserve the existing storage conventions and service boundaries. Inspect the actual implementation before changing persistence behavior.

---

## 4. Character and universe lifecycle

### Required user-facing workflow

1. A player has an account.
2. The player can create a character.
3. Character creation may produce an unassigned character.
4. The player can create a universe or select an eligible existing universe through a separate workflow.
5. The character is assigned to the selected universe.
6. The player can enter gameplay only after the character has a valid universe assignment.

### Invariants

- A character must not enter gameplay without a universe assignment.
- Character Creation and Universe Creation must remain separate screens/workflows.
- Display the player-entered universe seed, not the internal universe ID.
- Internal IDs remain implementation details.
- A player's character and universe operations must enforce account ownership.
- Do not allow one account to assign or manipulate another account's records.
- Assignment must respect lifecycle state and the project's locking/persistence conventions.
- Avoid destructive deletion of characters or universes. Character administration must not hard-delete records.
- Repeated assignment requests should be safe and must not create duplicate or inconsistent state.
- Do not silently move an already assigned character to a different universe.

### 0.1.84 milestone

The `0.1.84` patch implemented the character-to-universe assignment milestone. The delivered patch description recorded:

- A dedicated character-to-universe assignment service.
- Account ownership and lifecycle checks.
- Locking using the existing universe → character lock order.
- Initialization of starting location and exploration state.
- Revision tracking.
- Safe/idempotent repeated assignment handling.
- Prevention of reassignment to a different universe.

The user confirmed the resulting browser QA suite passed all 130 tests.

---

## 5. UI and product requirements

- Keep landing/menu, account, character selection, character creation, and universe creation as distinct workflows.
- Character Selection/Creation must not become a combined character/universe creation screen.
- Show the universe seed to the player, not its internal ID.
- Show character portrait and location information where appropriate.
- The Play action should only be available when the character is assigned to a valid universe.
- Do not use the term “frontier” in player-facing UI.
- Keep the workbench/application version separate from game-world data.
- Avoid redundant IDs and unnecessary visual borders.
- Ensure loading and Play flows complete reliably; do not introduce hanging or blank states.
- Keep CSS and JavaScript in their appropriate separate files rather than embedding large blocks in PHP views.

---

## 6. Development and delivery rules

These are project requirements, not optional style preferences.

### Source of truth

- Work from the newest verified source, not an assumed or stale GitHub snapshot.
- Before editing, establish the exact source version and inspect the relevant services, controllers, views, APIs, storage conventions, and tests.
- The user's working copy and reported QA result take precedence over an older repository snapshot.
- Never claim a test passed unless it was actually run against the exact source being delivered.
- Clearly distinguish tests run by the assistant from tests reported by the user.

### Patch delivery

- When code is requested and the change is ready, deliver a downloadable ZIP patch automatically.
- Do not make the user ask repeatedly for the ZIP.
- Include complete replacement files, not fragments that require manual insertion.
- Include concise patch/install notes and a list of changed files.
- Keep patches focused and small where practical.
- Do not include player storage, account records, passwords, secrets, logs, backups, or generated world/character data unless specifically required and explicitly approved.
- Do not overwrite or migrate user data as an incidental part of a code patch.
- Do not modify the live installation. Deliver a patch for the user to apply and test.
- Preserve existing functionality and backward compatibility unless a change is explicitly agreed.

### Validation

For each code patch, run the relevant checks against the exact patch source:

1. Browser QA endpoint.
2. CLI test suite.
3. PHP syntax checks across changed PHP files (and broader lint where practical).
4. JavaScript syntax checks across changed JS files.
5. Additional focused regression tests for the behavior changed.

Report actual counts and failures. Do not hide failures or present unverified results as fact.

### Communication

- Be direct and concise.
- Avoid excessive preambles, repeated apologies, and claims of intent.
- Do the requested coding work rather than responding with planning-only text when the task is clear.
- Do not ask the user to upload source again if the required source is already available.
- If the exact current source is genuinely unavailable, state precisely what is available and what is missing. Do not pretend an older repository version is current.

---

## 7. Environment and workflow context

The user is a web developer and manages their own infrastructure.

Known development preferences:

- Local/server-side development rather than cloud IDEs.
- Proxmox-hosted Apache environment.
- LAN development share has previously been `\\192.168.1.80\\www\\core-game`.
- GitHub repository is used primarily as a backup/source-control location.
- VS Code is the primary editor.
- PHP, JavaScript, Bootstrap, and CSS are preferred.
- Free, open-source, and self-hosted solutions are preferred.

The LAN path and server details are historical context. Verify current configuration before giving commands that depend on them.

---

## 8. Known prior QA discrepancy

An earlier QA report for a purported `0.1.83` source showed 110 passed and 19 failed out of 129. The reported failures involved several UI/workbench markers and lifecycle/data-management behaviors.

The subsequently re-uploaded source archive identified itself as `0.1.83` and was tested in the project conversation. That archive returned:

- Browser QA: 129 passed, 0 failed.
- CLI QA: 69 passed, 0 failed.
- PHP syntax: 83 files clean.
- JavaScript syntax: 4 files clean.

The earlier 19-failure report did not reproduce against that archive. It was treated as a stale or different source snapshot and was not patched blindly.

The `0.1.84` patch and user-confirmed 130-test result superseded that older baseline; `0.1.85` is now the latest assistant-validated patch.

---

## 9. Next development work

The character-to-universe assignment milestone was completed in `0.1.84`; account authorization and CSRF protection were added in `0.1.85`.

The next feature should be selected by reviewing the current project roadmap and actual source, then implementing the next agreed milestone. Do not invent a feature or assume an undocumented sequence.

Before starting the next patch:

1. Establish access to the exact latest verified source (`0.1.85` at this update).
2. Verify the version marker and inspect the assignment implementation.
3. Review the current tests and identify the next incomplete milestone.
4. Make a focused change that preserves existing behavior.
5. Add regression coverage.
6. Run the validation suite.
7. Package the complete patch ZIP.

**Do not base the next patch on GitHub `main` while it still reports `0.1.83`.**

---

## 10. Baseline summary

| Item | Current project state |
|---|---|
| Project | CORE |
| Repository | `mudgw2/Core-Game` |
| Accepted working baseline | `0.1.85` |
| User-confirmed browser QA (`0.1.84`) | 130 passed |
| Assistant-run browser QA (`0.1.85`) | 136 passed, 0 failed |
| Assistant-run CLI QA (`0.1.85`) | 76 passed, 0 failed |
| Character creation without universe | Supported |
| Character-to-universe assignment | Implemented in 0.1.84 patch |
| Play requires universe assignment | Required invariant |
| Persistence | JSON / flat files initially |
| Database | No MySQL dependency at this stage |
| Backend priority | Data integrity, stability, extensibility |
| 3D direction | Babylon.js; low-poly primitives first |
| Patch format | Downloadable ZIP with complete files |
| GitHub `main` observed version | `0.1.83` (stale relative to accepted baseline) |

