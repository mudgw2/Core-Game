# CORE 0.1.194 — Legacy Geography Compatibility

## Terrain initialization fix
CORE 0.1.193 expected every saved geography record to contain `planet.radius_km`. Existing worlds can have schema-version-1 geography records created before that field existed. Terrain generation therefore failed when opening those worlds.

CORE now resolves physical radius using the saved geography value when present. For legacy records without it, CORE derives the same radius from the generated world's size code (size 0 = 80 km; otherwise size × 800 km).

The compatibility path is shared by terrain and climate generation. It uses a derived in-memory value and does not rewrite, migrate, or replace the saved geography record.

## Verification
- Automated regression suite: 180 PASS / 0 FAIL.
- PHP syntax checks: PASS.
- Browser/runtime verification in the installed environment: pending.
