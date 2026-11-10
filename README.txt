CORE 0.1.86 — Character Ability Generation

PATCH CONTENTS
This focused patch adds the data-driven ability point-buy rules, species adjustments, Character Creation UI, server-side validation, regression tests, and release documentation. It does not include storage/data, storage/logs, storage/backups, account records, generated universes or characters, or Git metadata.

INSTALLATION
1. Back up the current CORE source.
2. Extract this ZIP into the CORE project root, preserving the directory structure.
3. Do not overwrite storage/data, storage/logs, storage/backups, or .git. This patch does not contain files from those locations.
4. Confirm config/version.php reports 0.1.86.
5. Test Human, Android, and Ysoki character creation. Confirm the point counter updates, species adjustments preview correctly, and over-budget scores are rejected.
6. Run php tests/run.php and POST api/qa.php from the trusted development/private network.

RULES BEHAVIOR
- Ability scores are purchased from 7 through 16 before species adjustments.
- The configured point-buy budget is 10 points.
- The server recalculates costs and species adjustments; client-side values are display-only.
- Human: +2 to one selected ability (Strength is the compatibility default when no selection is supplied by older internal callers).
- Android: +2 Dexterity, +2 Intelligence, -2 Charisma.
- Ysoki: +2 Dexterity, +2 Intelligence, -2 Strength.
- Character records retain base_attributes and final attributes separately. Existing gameplay reads continue to use attributes.

VALIDATION RUN AGAINST THIS PATCH
- CLI QA: 78 passed, 0 failed.
- Browser QA: 138 passed, 0 failed (HTTP 200).
- PHP syntax: 86 project PHP files passed.
- JavaScript syntax: 5 project JavaScript files passed.

No live installation was modified. Apply the patch to a development copy first.
