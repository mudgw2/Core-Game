# CORE 0.1.205 — UWP-Driven Planetary Atmosphere

This update connects planetary sky and visibility effects to the world's existing UWP atmosphere classification.

## Included
- UWP atmosphere codes 0-F are mapped to reusable optical profiles.
- Daylight sky scattering scales with atmospheric class.
- Local planetary fog/haze density scales with atmospheric class and remains separate from celestial rendering.
- Stars fade according to daylight and atmospheric optical depth. Vacuum retains visible stars during daytime; dense atmospheres produce stronger fading.
- Atmospheric profile data is included in the astronomical environment's time-state diagnostics.
- UWP alone does not define exact gas composition or sky color. A-F profiles therefore remain generic until explicit composition data is available.

## Compatibility and preservation
- Existing UWP/world generation remains authoritative and unchanged.
- No world, universe, character, account, geography, or terrain records are modified.
- Existing terrain geometry, dry-land spawn validation, movement/fly controls, and water rendering are unchanged.
- Celestial objects continue to opt out of planetary scene fog, preserving the accepted celestial isolation behavior.

## Verification
- Run `php tests/run.php` after extraction.
- Browser visual verification is still required for sky colors, atmospheric haze, and daytime star visibility across multiple UWP classes.
