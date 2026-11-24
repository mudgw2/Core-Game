CORE 0.1.124 — Hierarchical Universe Generation

Install by extracting the included files into the CORE project root, preserving paths.
This patch contains application source, tests, and release notes only. It does not include player data, accounts, credentials, backups, logs, or node_modules.

Backend changes:
- Advances procedural universe generation to version 3 and introduces the explicit Universe → Constellation → System hierarchy.
- Generates deterministic Constellation identities and seeds from the Universe identity.
- Generates independently seeded star Systems within each Constellation, with parent Constellation IDs/seeds and primary-star metadata.
- Persists nested Constellations with their child Systems, plus a flattened baseline.systems index for existing navigation, travel, scan, and multiplayer services. The flat index contains actual star Systems.
- Extends deterministic World profiles with parent System and Constellation identities/seeds.
- Initializes and updates character current_constellation_id and location.constellation_id alongside current_system_id during creation, assignment, movement, and route advancement.
- Adds Constellation counts to data-management inventory and stores hierarchy_version in the universe record.
- Fixes deployment-prefix normalization when Apache DOCUMENT_ROOT is already the CORE root and SCRIPT_NAME includes the mounted /public/index.php front controller.

Compatibility:
- Existing universe records are not rewritten. Existing saved records retain their stored baseline and IDs; newly created universes use generator version 3.
- Existing consumers can continue using baseline.systems as the flat star-System index. A future explicit legacy-universe migration/adapter is still required before old saved universe baselines can expose the new nested hierarchy without regeneration.

Validation:
- PHP QA suite: 118 passed, 0 failed.
- PHP syntax checks: passed for all PHP files.
- Automated tests cover deterministic nested hierarchy, parent-child IDs, star metadata, flat index consistency, and World parent identity.
- Browser visual testing was not performed.
