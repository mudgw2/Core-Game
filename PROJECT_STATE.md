# CORE — Project State

**Document purpose:** Authoritative continuity record for CORE Game 1.0.
**Last updated:** 2026-11-12
**Latest patch prepared:** `0.1.90` (deterministic system-world generation)
**Last user-confirmed baseline:** `0.1.89`
**Repository:** `mudgw2/Core-Game` (private GitHub repository)
**Persistent project location:** Personal Library `/Core Game/`
**Current source archive:** `admin.zip` supplied by the user on 2026-11-12

---

## 1. Project overview

CORE is a personal, single-player science-fiction sandbox inspired by EVE, Astrox Imperium, Starfinder 1E, and Traveller. It prioritizes a stable, extensible backend and persistent data model before expanding gameplay and presentation. It is not intended to be an MMO.

Core goals:
- Procedural universe generation driven by a user-provided seed.
- Characters must belong to a universe before entering gameplay.
- Character creation and universe creation remain separate workflows.
- Traveller/UWP-like world and system data.
- Star-by-star travel, scanning, exploration, and persistent discoveries.
- Starting locations such as a world, city, station, or outpost.
- Ship, officer, and crew management.
- Industry, piracy, bounty work, and exploration.
- Data-driven game values and definitions, primarily under `data/`.

## 2. Current release and verified QA

### Last user-confirmed baseline: 0.1.89

The user supplied the latest source archive and confirmed the 0.1.89 consistency patch with 141 tests passed. Treat 0.1.89 as the accepted baseline. Version 0.1.90 adds deterministic Traveller-inspired world profiles as a runtime-derived view; installation QA is pending.

The user confirmed:
> PASS — 141 tests passed.

This is the accepted user-verified QA result for CORE 0.1.89. Do not downgrade the baseline to 0.1.86 or earlier.

Release history:

| Version | Scope recorded in release delivery | User QA confirmation |
|---|---|---|
| 0.1.84 | Character-to-universe assignment | PASS — 130 tests |
| 0.1.85 | Account authorization and ownership enforcement | PASS — 136 tests |
| 0.1.86 | Character creation ability generation | PASS — 138 tests |
| 0.1.88 | Account archival and player workflow baseline | User browser QA: 138 passed / 3 failed |
| 0.1.89 | Cache versioning, public player identity/copy, and gameplay workflow cleanup | PASS — 141 tests passed (user-confirmed) |
| 0.1.90 | Deterministic Traveller-inspired world profiles, exposed in gameplay system inspection | Assistant QA: CLI 84/0; browser 143/0; user installation QA pending |

The 0.1.86 delivery described:
- 10-point ability score allocation.
- Base scores from 7–16 with configurable point costs.
- Species adjustments for Human, Android, and Ysoki.
- Live point counter and final-score preview.
- Server-side validation against over-budget allocations.
- Separate storage of base scores and species-adjusted scores.
- Preservation of existing editable character content definitions.

The 0.1.85 delivery described:
- Centralized player API authentication and authorization.
- Character reads and mutations restricted to the owning account.
- Universe navigation and lifecycle operations restricted to eligible account characters.
- CSRF validation for authenticated API writes.
- Account ownership recorded during character creation.
- Character listings filtered by account.
- Trusted-LAN Workbench access preserved.
- No hard deletion of player characters.

The 0.1.84 delivery described:
- Dedicated character-to-universe assignment service.
- Account ownership and lifecycle checks.
- Existing universe → character lock order.
- Starting location and exploration-state initialization.
- Revision tracking and idempotent assignment.
- Prevention of reassignment to a different universe.

Only the user-reported browser QA results above are treated as independently user-verified. Any assistant-run CLI, lint, or JavaScript checks must be reported separately and only when actually run against the exact release source.

## 3. Source of truth and persistent file locations

The persistent Library was inspected on 2026-11-09. It contains these CORE assets:

| Asset | Persistent Library path | Purpose |
|---|---|---|
| `CORE-0.1.86-character-creation.zip` | `/Core Game 1.0/CORE-0.1.86-character-creation.zip` | Latest delivered 0.1.86 release archive |
| `CORE-0.1.85-account-authorization.zip` | `/Core Game 1.0/CORE-0.1.85-account-authorization.zip` | Previous release archive |
| `CORE-0.1.84-character-universe-assignment.zip` | `/Core Game/CORE-0.1.84-character-universe-assignment.zip` | Previous release archive |
| `.git.zip` | `/Core Game/.git.zip` | Git metadata/archive supplied for source history |
| `PROJECT_STATE.md` | `/Core Game/PROJECT_STATE.md` | This continuity record |
| `admin(1).zip` | `/Core Game/admin(1).zip` | Earlier uploaded source archive; not the current baseline |

**Retrieval procedure for future sessions:**
1. Browse the persistent Library (`files.list`, `surface="library"`, recursive) before concluding that project files are unavailable.
2. Locate the latest CORE archive by version and exact filename. Do not rely on semantic search alone; it previously failed to surface the archive.
3. Retrieve/extract the exact archive through an authorized raw-file path when available. A Library listing proves the file exists, but does not itself prove its bytes are accessible to the current runtime.
4. If raw-byte materialization is denied, use the available Library/Project file connector route or clearly state the precise access limitation. Do not ask the user to re-upload a file merely because one retrieval method failed.
5. Verify `config/version.php`, source contents, and tests after extraction.
6. GitHub `main` was previously observed at 0.1.83 and must not be treated as current unless rechecked and synchronized.

The latest archive was found in `/Core Game 1.0/`, not under `/Core Game/`. The earlier assistant incorrectly reported it unavailable because it failed to browse the Library.

## 4. Technology and architecture

- PHP with MVC/OOP.
- JavaScript for client-side behavior.
- Bootstrap with project-specific CSS.
- JSON and flat-file persistence initially; no MySQL dependency in this 1.0 code line.
- REST-style API endpoints as the application develops.
- Babylon.js is the intended interactive 3D engine. Do not introduce Three.js.
- Begin with low-poly primitive geometry; GLB assets can be introduced later.

Persistence principles:
- Stable record IDs separate from user-facing seeds.
- Generated baseline data separated from persistent player state.
- Per-record locking for concurrent writes.
- State revision tracking and event/history records where appropriate.
- Safe, atomic JSON/flat-file persistence.
- Data-driven definitions and balance values.

Preserve existing storage conventions and service boundaries. Inspect implementation before changing persistence behavior.

## 5. Character and universe lifecycle

Required workflow:
1. Player has an account.
2. Player can create a character.
3. Character creation may produce an unassigned character.
4. Player separately creates or selects an eligible universe.
5. Character is assigned to the selected universe.
6. Gameplay is available only after a valid assignment.

Invariants:
- No gameplay without a valid universe assignment.
- Character Creation and Universe Creation remain separate workflows.
- Display the player-entered universe seed, not the internal universe ID.
- Enforce account ownership for character and universe operations.
- Assignment follows lifecycle, locking, and persistence conventions.
- Do not hard-delete characters or universes.
- Repeated assignment requests must be safe and idempotent.
- Do not silently move an assigned character to another universe.

## 6. Character creation and species

The 0.1.86 release introduced ability score allocation:
- 10-point allocation budget.
- Base ability scores range from 7 through 16.
- Point costs are configurable.
- Human receives +2 to one chosen ability.
- Android receives +2 Dexterity, +2 Intelligence, −2 Charisma.
- Ysoki receives +2 Dexterity, +2 Intelligence, −2 Strength.
- UI provides live point accounting and final-score preview.
- Server validates the allocation; over-budget allocations are rejected.
- Base and species-adjusted scores are stored separately.

These details are the delivered release description. Verify the exact data definitions and implementation in the source before extending or changing species rules.

## 7. Account security

The 0.1.85 release description records centralized player API authentication/authorization, account ownership checks for character operations, CSRF validation for authenticated writes, account-filtered character listings, and preservation of trusted-LAN Workbench access.

Before changing access control, inspect all API routes and the existing trusted Workbench rules. Do not broaden or weaken access as an incidental side effect.

## 8. UI and product requirements

- Keep landing/menu, account, character selection, character creation, and universe creation as distinct workflows.
- Do not combine character and universe creation into one screen.
- Show universe seed, not internal universe ID.
- Show character portrait and location information where appropriate.
- Play is available only when the character is assigned to a valid universe.
- Do not use “frontier” in player-facing UI.
- Keep workbench/application version separate from game-world data.
- Avoid redundant IDs and unnecessary visual borders.
- Ensure loading and Play flows complete reliably.
- Keep CSS and JavaScript in separate files rather than embedding large blocks in PHP views.

## 9. Development and delivery rules

These are mandatory project requirements.

### Source control and continuity
- Work from the newest verified source archive in the persistent Library, not an assumed or stale GitHub snapshot.
- Check the Library before saying files are unavailable.
- Establish exact source version and inspect relevant services, controllers, views, APIs, storage, and tests before editing.
- The user's working copy and user-reported QA result take precedence over an older repository snapshot.
- Never claim a test passed unless it was actually run against the exact source being delivered.
- Distinguish assistant-run tests from user-reported results.

### Patch delivery
- When code is requested and ready, automatically deliver a downloadable ZIP patch. Do not make the user ask for it.
- Include complete replacement files, not fragments requiring manual insertion.
- Include concise install notes, release notes, and changed-file list.
- Keep patches focused and small where practical.
- Exclude player storage, account records, passwords, secrets, logs, backups, and generated world/character data unless specifically required and explicitly approved.
- Do not overwrite or migrate user data as an incidental part of a code patch.
- Do not modify the live installation.
- Preserve existing functionality and backward compatibility unless explicitly agreed.

### Validation
Run relevant checks against the exact patch source:
1. Browser QA endpoint.
2. CLI test suite.
3. PHP syntax checks for changed files and broader lint where practical.
4. JavaScript syntax checks for changed files.
5. Focused regression tests for changed behavior.

Report actual counts and failures. Never invent results.

### Communication
- Be direct and concise.
- Avoid excessive preambles and repeated apologies.
- Do the requested coding work rather than planning-only responses when scope is clear.
- Do not ask the user to upload source already present in the Library.
- If the exact archive's raw bytes are inaccessible, report that precise technical limitation after trying the Library retrieval path; do not claim the archive is absent.

## 10. Environment and workflow context

The user is a web developer and manages their own infrastructure.
- Local/server-side development rather than cloud IDEs.
- Proxmox-hosted Apache environment.
- Historical LAN development share: `\\192.168.1.80\\www\\core-game`.
- GitHub repository `mudgw2/Core-Game` is used primarily as backup/source control.
- VS Code is the primary editor.
- PHP, JavaScript, Bootstrap, and CSS are preferred.
- Free, open-source, and self-hosted solutions are preferred.

Verify current server configuration before giving environment-specific commands.

## 11. Current development milestone

The current source explicitly identifies world-level exploration as a missing layer: star systems exist, but their worlds are not generated. The long-term project goals also call for Traveller/UWP-like world data. Version 0.1.90 implements this next foundation step. No separate roadmap file was present in the supplied source archive.

0.1.90 world generation is deliberately derived at runtime from the universe seed and stable system ID. It does not rewrite existing universe records or require a storage migration. Each system receives 1–9 deterministic world profiles with stable IDs, orbital slots, world classifications, and eight-character UWP profiles. The gameplay inspection panel displays the worlds for the selected system. Assistant validation against this exact patch: CLI 84 passed / 0 failed; browser QA 143 passed / 0 failed; PHP and JavaScript syntax sweeps passed; player-facing prohibited-term scan clean.

After user QA confirms 0.1.90, select the next milestone by reviewing the then-current source and tests; do not assume a feature sequence beyond the stated project goals.

## 12. Baseline summary

| Item | Current state |
|---|---|
| Project | CORE Game 1.0 |
| Repository | `mudgw2/Core-Game` |
| Last user-confirmed baseline | `0.1.89` (0.1.90 pending user QA) |
| User-confirmed QA | 0.1.89: 141 passed |
| 0.1.85 browser QA | 136 passed |
| 0.1.84 browser QA | 130 passed |
| Character creation without universe | Supported |
| Character-to-universe assignment | Implemented in 0.1.84 |
| Account authorization milestone | Delivered in 0.1.85 |
| Ability allocation milestone | Delivered in 0.1.86 |
| Persistence | JSON / flat files initially |
| Database | No MySQL dependency in this code line |
| 3D direction | Babylon.js; low-poly primitives first |
| Patch format | Downloadable ZIP with complete files |
| Latest source archive | `admin.zip` supplied 2026-11-12; 0.1.89 verified by user |
| GitHub `main` | Previously observed at 0.1.83; recheck before use |
