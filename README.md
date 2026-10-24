# CORE PHP Foundation 0.1.30 — Universe Lifecycle Controls

CORE is a seed-driven browser-based sci-fi sandbox foundation using PHP, JSON flat-file persistence, and an accessible browser Workbench.

## 0.1.30 highlights

- Reversible universe **disable/enable** and **lock/unlock** controls.
- Server-side enforcement blocks character creation, movement, travel, scanning, discovery investigation, crafting, fitting, and universe-state mutations while the universe is disabled or locked.
- Existing characters and saved universe state remain available for inspection; lifecycle controls do not delete data or alter the universe event revision.
- Independent lifecycle revision checks, idempotent no-op actions, error/warning logging, and Workbench status feedback.
- Character-level lifecycle controls and deletion remain available as separate operations.

## Install

Extract the release into the existing application directory using your established deployment workflow. Preserve existing `storage/data/` and `storage/logs/` contents. The ZIP contains placeholder files only; it does not include live storage data.

## Verify

Open Workbench → Operations & QA → Run automated tests. Expected result: **86 browser QA tests passed**. The CLI suite contains 11 tests (`php tests/run.php`) as a supplementary developer check.

## API

`POST /api/universe/lifecycle` with JSON `{"universe_id":"...","action":"lock","expected_revision":0}`. Actions are `disable`, `enable`, `lock`, and `unlock`. Lifecycle revision is independent of the universe event-state revision.

Universe lifecycle controls are reversible. They are not authentication or authorization controls; keep the development Workbench and development-only endpoints restricted to a trusted network.
