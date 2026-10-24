# CORE PHP Foundation 0.1.40

CORE is a seed-driven sci-fi sandbox foundation using PHP, JSON persistence, and a browser Workbench.

## 0.1.40: data-driven game content

Exploration signals, their outcomes and rewards, item definitions, crafting recipes, and ship-module definitions are stored as editable JSON under `config/content/`. PHP services load those files at runtime, validate cross-references, and use them for scanning, investigation rewards, crafting, and fitting. The Workbench reads the same definitions through the API and populates its recipe/module selectors dynamically.

### Content files

- `config/content/items.json`
- `config/content/discoveries.json`
- `config/content/recipes.json`
- `config/content/ship-modules.json`

Stable IDs in these files are referenced by player inventory, scan results, reward ledgers, recipes, and fitted ship modules. Keep IDs stable when changing labels or balancing quantities; changing IDs may orphan references in existing character records.

### Content API

- `GET /api/content`
- `GET /api/content/items`
- `GET /api/content/recipes`
- `GET /api/content/discoveries`
- `GET /api/content/ship-modules`

Wrappers are provided at `api/content.php`, `api/content-items.php`, `api/content-recipes.php`, `api/content-discoveries.php`, and `api/content-ship-modules.php`.

### Saved player state

Each character's full current state is persisted to `storage/data/characters/<character-id>.json`. This includes inventory totals, inventory ledger, scan discoveries and investigation results, explored systems, current location, and ship fittings. Read-only APIs:

- `GET /api/characters/record?character_id=<character-id>`
- `GET /api/characters?universe_id=<universe-id>`

Character-state APIs remain limited to development mode on a local/private network. Use revision-checked gameplay endpoints to change player state; the record-read endpoint does not permit writes.

## Run tests

From the project directory:

```sh
php tests/run.php
```

The Workbench's **Run automated tests** action runs the browser QA suite. Do not expose development-only QA or data-management routes to the public internet.

## Upgrade safety

When installing an upgrade, preserve the existing live contents of `storage/data/`, `storage/logs/`, and `storage/backups/`. The package's placeholder storage directories must not replace or erase live data. Content changes belong under `config/content/`; keep stable content IDs to preserve references from existing characters.

See `RELEASE-NOTES-0.1.40.md` for details and validation results.
