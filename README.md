# CORE PHP Foundation 0.1.41

CORE is a seed-driven sci-fi sandbox foundation using PHP, JSON persistence, and a browser Workbench.

## Data-driven game content

Game definitions live in editable JSON files under `config/content/`, not duplicated PHP catalogs:

- `items.json` — stable item IDs, labels, categories, descriptions, stackability.
- `discoveries.json` — exploration signals, outcomes, reward item IDs and quantity ranges, scan signal-count limits.
- `recipes.json` — crafting inputs and outputs.
- `ship-modules.json` — module slots and installation requirements.

The PHP mechanics load these definitions at runtime. The Workbench **Content Management** tab lets a developer load, validate, and save the JSON catalogs without editing PHP. Validation checks catalog structure and cross-catalog references before any write.

## Content API

Read-only endpoints:

- `GET /api/content`
- `GET /api/content/items`
- `GET /api/content/recipes`
- `GET /api/content/discoveries`
- `GET /api/content/ship-modules`

Development-only, local/private-network mutation endpoints:

- `POST /api/content/validate` with `{ "catalog": "recipes", "document": { ... } }` — validates a draft without saving.
- `POST /api/content/save` with `{ "catalog": "recipes", "expected_revision": 1, "document": { ... } }` — validates and saves if the revision matches.

PHP wrappers: `api/content.php`, `api/content-items.php`, `api/content-recipes.php`, `api/content-discoveries.php`, `api/content-ship-modules.php`, `api/content-validate.php`, `api/content-save.php`.

Every successful save creates a verified previous-version copy under `storage/backups/content/`, increments the catalog revision, and atomically replaces the JSON file. Stale revisions are rejected. Content definitions are re-read by gameplay services on each request, so changes take effect without a PHP code change or application restart.

Keep content IDs stable when changing labels or balancing quantities. Existing character inventory, reward ledgers, scan results, recipes, and fitted modules may refer to those IDs.

## Saved player state

Each character's full state is persisted in `storage/data/characters/<character-id>.json`, including inventory, transaction ledger, scan/discovery state, exploration history, current location, fittings, revision, and lifecycle state. Read-only APIs:

- `GET /api/characters/record?character_id=<character-id>`
- `GET /api/characters?universe_id=<universe-id>`

Character-state APIs remain restricted to development mode on a local/private network. Use revision-checked gameplay endpoints to change player state; record-read endpoints do not permit arbitrary writes.

## Run tests

```sh
php tests/run.php
```

The Workbench's **Run automated tests** action runs the browser QA suite. Do not expose development-only QA or data-management routes to the public internet.

## Upgrade safety

Preserve the live contents of `storage/data/`, `storage/logs/`, and `storage/backups/` when installing an upgrade. The package's placeholder storage directories must not replace or erase live data. Content backups are stored under `storage/backups/content/` and should also be retained.

See `RELEASE-NOTES-0.1.41.md` for changes and validation results.
