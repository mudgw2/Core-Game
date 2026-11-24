(() => {
  'use strict';

  const DEFAULTS = Object.freeze({ version: 1, tint: '#61d8e8', saturation: 100, brightness: 100 });
  const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

  function normalize(value) {
    const source = value && typeof value === 'object' ? value : {};
    const tint = /^#[0-9a-f]{6}$/i.test(String(source.tint || '')) ? String(source.tint).toLowerCase() : DEFAULTS.tint;
    const saturation = Number.isFinite(Number(source.saturation)) ? clamp(Math.round(Number(source.saturation)), 0, 200) : DEFAULTS.saturation;
    const brightness = Number.isFinite(Number(source.brightness)) ? clamp(Math.round(Number(source.brightness)), 50, 150) : DEFAULTS.brightness;
    return { version: 1, tint, saturation, brightness };
  }

  function colorComponents(appearance) {
    const normalized = normalize(appearance);
    const hex = normalized.tint.slice(1);
    const r = parseInt(hex.slice(0, 2), 16) / 255;
    const g = parseInt(hex.slice(2, 4), 16) / 255;
    const b = parseInt(hex.slice(4, 6), 16) / 255;
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const delta = max - min;
    let h = 0;
    let s = 0;
    let l = (max + min) / 2;
    if (delta !== 0) {
      s = delta / (1 - Math.abs(2 * l - 1));
      if (max === r) h = ((g - b) / delta) % 6;
      else if (max === g) h = (b - r) / delta + 2;
      else h = (r - g) / delta + 4;
      h /= 6;
      if (h < 0) h += 1;
    }
    s = clamp(s * normalized.saturation / 100, 0, 1);
    l = clamp(l * normalized.brightness / 100, 0, 1);
    const chroma = (1 - Math.abs(2 * l - 1)) * s;
    const x = chroma * (1 - Math.abs((h * 6) % 2 - 1));
    const m = l - chroma / 2;
    let rr = 0, gg = 0, bb = 0;
    const sector = Math.floor(h * 6) % 6;
    if (sector === 0) [rr, gg, bb] = [chroma, x, 0];
    else if (sector === 1) [rr, gg, bb] = [x, chroma, 0];
    else if (sector === 2) [rr, gg, bb] = [0, chroma, x];
    else if (sector === 3) [rr, gg, bb] = [0, x, chroma];
    else if (sector === 4) [rr, gg, bb] = [x, 0, chroma];
    else [rr, gg, bb] = [chroma, 0, x];
    return [rr + m, gg + m, bb + m];
  }

  function applyToMaterial(material, appearance) {
    if (!material || !window.BABYLON) return;
    const [r, g, b] = colorComponents(appearance);
    material.diffuseColor = new BABYLON.Color3(r, g, b);
  }

  window.CORE_APPEARANCE = Object.freeze({ defaults: DEFAULTS, normalize, colorComponents, applyToMaterial });
})();
