# CORE 0.1.152 — Multiplayer Planetary Movement Axis Fix

## Fixed
- Corrected conversion of world-space movement direction into the server's planetary map coordinate frame.
- Movement is projected onto the local east and north tangent vectors at the character's current spherical position.
- East/west input now maps to server X (`strafe`); north/south input maps to server Z (`forward`).
- The conversion works from the current surface location rather than assuming world X/Z are equivalent to planetary east/north.

## Preserved
- Server-authoritative movement and bounded directional input.
- Persistent server-authoritative planetary clock from 0.1.151.
- Existing player and universe storage formats.
