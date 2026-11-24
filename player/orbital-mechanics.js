(() => {
  'use strict';

  // Shared, seed-independent orbital presentation. Orbital slots are the
  // authoritative ordering; spacing follows a geometric progression and all
  // planets remain on the same reference plane (small real systems' inclinations
  // are not represented in the current world data).
  window.CORE_ORBITS = Object.freeze({
    layout(worlds, minimumRadius, maximumRadius) {
      const entries = (Array.isArray(worlds) ? worlds : []).map((world, index) => ({
        world,
        slot: Math.max(1, Math.floor(Number(world.orbital_slot) || index + 1)),
        phase: (index * 2.399963229728653) % (Math.PI * 2)
      })).sort((a, b) => a.slot - b.slot);

      if (!entries.length) return [];
      const raw = entries.map(entry => Math.pow(1.55, entry.slot - 1));
      const first = raw[0];
      const last = raw[raw.length - 1];
      const span = Math.max(1, last - first);
      return entries.map((entry, index) => {
        const normalized = (raw[index] - first) / span;
        const radius = entries.length === 1
          ? (minimumRadius + maximumRadius) / 2
          : minimumRadius + normalized * (maximumRadius - minimumRadius);
        return {
          world: entry.world,
          slot: entry.slot,
          phase: entry.phase,
          radius,
          angularSpeed: 0.22 / Math.sqrt(radius * radius * radius)
        };
      });
    }
  });
})();
