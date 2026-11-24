(() => {
  'use strict';

  let planetaryGeography = null;
  let planetaryTerrain = null;
  let planetaryClimate = null;
  let spawnHex = null;
  let planetRadius = 6400;
  let surfaceMesh = null;
  let waterMesh = null;
  let waterBasePositions = null;
  let waterPhase = 0;
  let waterUpdateAccumulator = 0;
  let spawnNormal = new BABYLON.Vector3(1, 0, 0);
  let localPatchMesh = null;
  let localPatchOffsets = null;
  let localPatchCenter = null;
  let localPatchBuild = null;
  let localPatchBuildScheduled = false;
  let localPatchRequestCenter = null;
  const localPatchBatchSize = 4096;
  let localPatchSizeKm = 512;
  const localPatchSegments = 320;
  let surfaceSeed = 'CORE';
  let hydrographics = 0.5;
  let terrainSeedNumber = 0;
  let terrainParameters = {};
  let terrainModifications = [];
  const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

  function hashLattice(x, y, z) {
    let value = Math.imul(x | 0, 374761393) + Math.imul(y | 0, 668265263) + Math.imul(z | 0, 2246822519) + terrainSeedNumber;
    value = Math.imul(value ^ (value >>> 13), 1274126177);
    value = Math.imul(value ^ (value >>> 16), 2246822519);
    value ^= value >>> 13;
    return (value >>> 0) / 4294967295;
  }

  function smooth(value) { return value * value * (3 - 2 * value); }

  function valueNoise3D(x, y, z) {
    const ix = Math.floor(x), iy = Math.floor(y), iz = Math.floor(z);
    const fx = smooth(x - ix), fy = smooth(y - iy), fz = smooth(z - iz);
    const x00 = hashLattice(ix, iy, iz) * (1 - fx) + hashLattice(ix + 1, iy, iz) * fx;
    const x10 = hashLattice(ix, iy + 1, iz) * (1 - fx) + hashLattice(ix + 1, iy + 1, iz) * fx;
    const x01 = hashLattice(ix, iy, iz + 1) * (1 - fx) + hashLattice(ix + 1, iy, iz + 1) * fx;
    const x11 = hashLattice(ix, iy + 1, iz + 1) * (1 - fx) + hashLattice(ix + 1, iy + 1, iz + 1) * fx;
    const y0 = x00 * (1 - fy) + x10 * fy;
    const y1 = x01 * (1 - fy) + x11 * fy;
    return y0 * (1 - fz) + y1 * fz;
  }

  function fractalNoise(normal, frequency, octaves, lacunarity = 2, gain = 0.5, offsets = [0, 0, 0]) {
    let amplitude = 1, total = 0, weight = 0, scale = frequency;
    for (let octave = 0; octave < octaves; octave++) {
      const sample = valueNoise3D(
        normal.x * scale + offsets[0] + octave * 17.13,
        normal.y * scale + offsets[1] + octave * 31.71,
        normal.z * scale + offsets[2] + octave * 11.37
      );
      total += sample * amplitude;
      weight += amplitude;
      amplitude *= gain;
      scale *= lacunarity;
    }
    return weight > 0 ? total / weight : 0.5;
  }

  function hashTerrainSeed(seed) {
    // World seeds commonly begin with labels such as WLD- or SYS-. Parsing
    // their first eight characters as hexadecimal collapses every such seed
    // to zero, making separate worlds share identical terrain. Hash the full
    // seed string so every persisted world gets a stable, distinct field.
    let hash = 2166136261;
    for (let index = 0; index < seed.length; index++) {
      hash ^= seed.charCodeAt(index);
      hash = Math.imul(hash, 16777619);
    }
    return hash >>> 0;
  }

  function initializeTerrain() {
    const seed = String(planetaryTerrain?.world?.terrain_seed || planetaryTerrain?.world?.seed || surfaceSeed);
    terrainSeedNumber = hashTerrainSeed(seed);
    terrainParameters = planetaryTerrain?.generator?.parameters || {};
    terrainModifications = Array.isArray(planetaryTerrain?.modifications) ? planetaryTerrain.modifications : [];
  }

  function surfaceField(normal) {
    const continentalFrequency = Math.min(Number(terrainParameters.continental_frequency) || 1.6, 1.8);
    const regionalFrequency = Math.min(Number(terrainParameters.regional_frequency) || 3.0, 3.2);
    const mountainFrequency = Math.min(Number(terrainParameters.mountain_frequency) || 5.5, 6.0);
    const detailFrequency = Math.min(Number(terrainParameters.detail_frequency) || 12, 13);
    const warpStrength = clamp(Number(terrainParameters.domain_warp_strength) || 0.22, 0, 0.8);
    // One smooth lattice sample per warp axis is sufficient here; the later
    // continental/regional fields provide the multi-octave structure.
    const warpX = valueNoise3D(normal.x * 1.65 + 13.1, normal.y * 1.65 + 2.7, normal.z * 1.65 + 9.4) - 0.5;
    const warpY = valueNoise3D(normal.x * 1.65 + 4.2, normal.y * 1.65 + 17.8, normal.z * 1.65 + 6.3) - 0.5;
    const warpZ = valueNoise3D(normal.x * 1.65 + 8.8, normal.y * 1.65 + 5.4, normal.z * 1.65 + 21.6) - 0.5;
    const warped = new BABYLON.Vector3(
      normal.x + warpX * warpStrength,
      normal.y + warpY * warpStrength,
      normal.z + warpZ * warpStrength
    ).normalize();
    const continental = fractalNoise(warped, continentalFrequency, 4, 2.05, 0.52, [2.3, 7.1, 11.9]);
    const regional = fractalNoise(warped, regionalFrequency, 4, 2.0, 0.5, [19.2, 3.8, 14.1]);
    const detail = fractalNoise(warped, detailFrequency, 3, 2.1, 0.48, [5.7, 23.4, 1.6]);
    const ridgeNoise = fractalNoise(warped, mountainFrequency, 4, 2.0, 0.52, [31.1, 12.4, 4.8]);
    const ridged = 1 - Math.abs(ridgeNoise * 2 - 1);
    const combined = continental * 0.68 + regional * 0.19 + detail * 0.055 + ridged * 0.075;
    // The blended value-noise field clusters near its midpoint. Expand that
    // distribution so hydrographics thresholds produce coherent land/ocean
    // proportions and enough upper-tail range for hills and mountain belts.
    return { field: clamp(0.5 + (combined - 0.5) * 3.0, 0, 1), regional, ridge: ridged };
  }

  function modificationElevation(normal) {
    if (!terrainModifications.length) return 0;
    const latitude = Math.asin(clamp(normal.y, -1, 1));
    const longitude = Math.atan2(normal.z, normal.x);
    let elevation = 0;
    for (const feature of terrainModifications) {
      if (!feature || typeof feature !== 'object') continue;
      const featureLat = Number(feature.latitude) * Math.PI / 180;
      const featureLon = Number(feature.longitude) * Math.PI / 180;
      const radiusKm = Number(feature.radius_km);
      if (!Number.isFinite(featureLat) || !Number.isFinite(featureLon) || !Number.isFinite(radiusKm) || radiusKm <= 0) continue;
      const cosine = clamp(Math.sin(latitude) * Math.sin(featureLat) + Math.cos(latitude) * Math.cos(featureLat) * Math.cos(longitude - featureLon), -1, 1);
      const distanceKm = Math.acos(cosine) * planetRadius;
      const normalized = distanceKm / radiusKm;
      if (normalized > 2.5) continue;
      const gaussian = Math.exp(-0.5 * normalized * normalized);
      const type = String(feature.type || 'elevation_stamp').toLowerCase();
      const height = Number(feature.height_km) || 0;
      const depth = Number(feature.depth_km) || 0;
      if (type === 'meteor_crater') {
        const craterBowl = -Math.abs(depth || Math.abs(height) * 0.55) * Math.exp(-4.5 * normalized * normalized);
        const rim = Math.abs(height) * Math.exp(-Math.pow((normalized - 0.82) / 0.16, 2));
        elevation += craterBowl + rim;
      } else if (type === 'volcano') {
        const cone = Math.abs(height || 2) * gaussian;
        const crater = depth > 0 ? -depth * Math.exp(-Math.pow(normalized / 0.2, 2)) : 0;
        elevation += cone + crater;
      } else {
        elevation += height * gaussian;
      }
    }
    return elevation;
  }

  function surfaceAtNormal(normal, includeLocalRelief = true, localReliefWeight = 1) {
    const fieldData = surfaceField(normal);
    const field = fieldData.field;
    const hydrographicsFraction = clamp(Number(terrainParameters.hydrographics_fraction ?? hydrographics), 0, 1);
    const water = hydrographicsFraction >= 1 ? true : (hydrographicsFraction <= 0 ? false : field < hydrographicsFraction);
    const normalizedLand = clamp((field - hydrographicsFraction) / Math.max(0.001, 1 - hydrographicsFraction), 0, 1);
    const elevationScale = Number(terrainParameters.elevation_scale_km) || 34;
    const oceanDepthScale = Number(terrainParameters.ocean_depth_scale_km) || 8;
    const mountainThreshold = Math.max(Number(terrainParameters.mountain_threshold) || 0.82, 0.80);
    const hillThreshold = Math.max(Number(terrainParameters.hill_threshold) || 0.58, 0.56);
    const regional = fieldData.regional;
    const ridge = fieldData.ridge;
    let terrain = water ? 'water' : (normalizedLand > mountainThreshold && ridge > 0.52 ? 'mountains' : (normalizedLand > hillThreshold ? 'hills' : 'plains'));
    let elevation = water
      ? -(0.12 + (1 - field) * oceanDepthScale)
      : (terrain === 'mountains'
        ? elevationScale * (0.16 + normalizedLand * 0.30) * (0.72 + ridge * 0.28)
        : terrain === 'hills'
          ? elevationScale * (0.025 + regional * 0.105)
          : elevationScale * (0.0015 + regional * 0.010));
    elevation += modificationElevation(normal);
    // Planet-scale sphere vertices are too widely spaced for first-person
    // terrain detail. Add deterministic kilometer-scale relief to sampled
    // surface positions; the close-range patch below renders these samples.
    if (includeLocalRelief && !water) {
      // Three smooth, geographically broad scales create actual relief on the
      // player-centered mesh. These are single-octave value-noise fields (not
      // fractal stacks), keeping patch rebuilds affordable while avoiding the
      // needle-like peaks caused by high-frequency ridged noise.
      // Frequencies correspond to roughly 500 km, 200 km, and 85 km features
      // on an Earth-sized world: broad plains, rolling regions, and restrained
      // surface breakup respectively.
      const broadDetail = valueNoise3D(normal.x * 80 + 41.7, normal.y * 80 + 8.2, normal.z * 80 + 17.9) - 0.5;
      const regionalDetail = valueNoise3D(normal.x * 200 + 7.4, normal.y * 200 + 53.1, normal.z * 200 + 29.6) - 0.5;
      const fineDetail = valueNoise3D(normal.x * 470 + 18.6, normal.y * 470 + 4.7, normal.z * 470 + 61.2) - 0.5;
      const detailStrength = terrain === 'mountains' ? 18.0 : (terrain === 'hills' ? 12.0 : 7.0);
      // Keep the detailed patch on or above the coarse planet mesh. Without
      // this small positive datum, negative local relief falls behind the
      // underlying sphere; the sphere then visually covers the patch while
      // movement still samples the lower height, making the avatar sink. The
      // edge fade returns this lift smoothly to zero at the patch boundary.
      const landform = 0.8 + broadDetail * 0.85 + regionalDetail * 0.5 + fineDetail * 0.2;
      elevation += landform * detailStrength * clamp(localReliefWeight, 0, 1);
    }
    // The persisted elevation remains in kilometers. The relief multiplier is
    // deliberately kept moderate; visible close-range relief comes from the
    // multi-scale landform field above, not from amplifying every planetary
    // elevation category into extreme mountain walls.
    const reliefExaggeration = clamp(Number(terrainParameters.relief_exaggeration) || 2.5, 1.0, 2.75);
    elevation *= reliefExaggeration;
    let color;
    if (water) {
      const depth = clamp((hydrographicsFraction - field) / Math.max(0.001, hydrographicsFraction), 0, 1);
      const shallow = [0.035, 0.42, 0.43];
      const deep = [0.012, 0.055, 0.19];
      color = shallow.map((value, index) => value * (1 - depth) + deep[index] * depth);
    } else if (terrain === 'mountains') {
      color = [.48, .46, .42];
    } else if (terrain === 'hills') {
      color = [.31, .39, .25];
    } else if (normalizedLand < .13) {
      color = [.66, .56, .37];
    } else {
      color = [.38, .49, .30];
    }
    // Stable, world-space surface breakup adds visual texture without extra
    // geometry or image assets. The same coordinates always produce the same
    // coloration, including after a terrain patch is rebuilt.
    const textureNoise = valueNoise3D(normal.x * 360 + 71.3, normal.y * 360 + 19.7, normal.z * 360 + 43.1) - 0.5;
    const facetNoise = valueNoise3D(normal.x * 920 + 11.9, normal.y * 920 + 83.2, normal.z * 920 + 5.6) - 0.5;
    const textureStrength = water ? 0.055 : (terrain === 'mountains' ? 0.105 : 0.075);
    color = color.map((channel, index) => clamp(channel + textureNoise * textureStrength + facetNoise * textureStrength * 0.45, 0, 1));
    return { elevation, water, terrain, color, field };
  }

  async function load(boot) {
    const params = new URLSearchParams({
      universe_id: String(boot.universeId || ''),
      system_id: String(boot.system?.id || ''),
      world_id: String(boot.world?.id || '')
    });
    const apiRoot = String(boot.apiRoot || '').replace(/\/$/, '');
    const geographyEndpoint = `${apiRoot}/api/world-geography.php?${params.toString()}`;
    const terrainEndpoint = `${apiRoot}/api/world-terrain.php?${params.toString()}`;
    async function readPayload(endpoint, label) {
      const response = await fetch(endpoint, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(20000) });
      const raw = await response.text();
      let payload;
      try { payload = JSON.parse(raw); } catch { throw new Error(`${label} API returned invalid JSON (HTTP ${response.status}).`); }
      if (!response.ok || payload.ok !== true) throw new Error(payload.error?.message || payload.error?.code || `${label} could not be loaded (HTTP ${response.status}).`);
      return payload;
    }
    const geographyPayload = await readPayload(geographyEndpoint, 'Planetary geography');
    const geography = geographyPayload.geography;
    if (!geography?.map?.hexes?.length) throw new Error('The saved planetary geography contains no usable legacy reference hexes.');
    const terrainPayload = await readPayload(terrainEndpoint, 'Planetary terrain');
    if (!terrainPayload.terrain?.generator?.parameters || !Array.isArray(terrainPayload.terrain.modifications)) {
      throw new Error('The saved planetary terrain definition is incomplete or unsupported.');
    }
    const hexes = geography.map.hexes.filter(hex => Number.isFinite(Number(hex.q)) && Number.isFinite(Number(hex.r)));
    if (!hexes.length) throw new Error('The saved planetary geography contains no usable legacy reference hexes.');
    const byId = new Map(hexes.map(hex => [String(hex.id), hex]));
    const preferredId = geography.locations?.population_centers?.find(site => site.type === 'primary')?.hex_id
      || geography.locations?.spaceports?.[0]?.hex_id;
    spawnHex = byId.get(String(preferredId || '')) || hexes[0];
    planetaryGeography = { ...geography, map: { ...geography.map, hexes }, hexById: byId };
    planetaryTerrain = terrainPayload.terrain;
    planetaryClimate = terrainPayload.climate || null;
    surfaceSeed = String(geography.world?.seed || boot.world?.seed || boot.world?.id || 'CORE');
    hydrographics = clamp(Number(geography.world?.hydrographics ?? boot.world?.hydrographics ?? 5), 0, 10) / 10;
    const storedRadius = Number(geography.planet?.radius_km);
    const size = Number(geography.world?.size ?? boot.world?.size ?? 8);
    planetRadius = Number.isFinite(storedRadius) && storedRadius > 0 ? storedRadius : Math.max(80, size * 800);
    // Size the local surface mesh to extend beyond the geometric horizon at
    // the camera's maximum third-person zoom (24 km), with a 100 km margin.
    // This prevents the player-centered patch edge from appearing as a nearby
    // straight horizon when the camera is pulled back.
    const expectedHorizonKm = Math.sqrt(2 * planetRadius * 26);
    // Extend the detailed surface well beyond the maximum third-person horizon.
    // The added margin keeps the square patch edge outside the visible skyline.
    localPatchSizeKm = Math.max(512, Math.ceil((2 * (expectedHorizonKm + 260)) / 100) * 100);
    initializeTerrain();
    const primarySite = geography.locations?.population_centers?.find(site => site.type === 'primary')
      || geography.locations?.spaceports?.[0] || null;
    const primaryHex = primarySite?.hex_id ? byId.get(String(primarySite.hex_id)) : null;
    const columns = Number(geography.map.columns || 20);
    const rows = Number(geography.map.rows || 16);
    const preferredLatitude = primaryHex ? 90 - ((Number(primaryHex.r) + 0.5) / rows) * 180 : 0;
    const preferredLongitude = primaryHex ? ((Number(primaryHex.q) + 0.5) / columns) * 360 - 180 : 0;
    spawnNormal = findDrySpawnNormal(preferredLatitude, preferredLongitude);
    return planetaryGeography;
  }

  function normalFromCoordinates(latitude, longitude) {
    const lat = Number(latitude) * Math.PI / 180;
    const lon = Number(longitude) * Math.PI / 180;
    return new BABYLON.Vector3(Math.cos(lat) * Math.cos(lon), Math.sin(lat), Math.cos(lat) * Math.sin(lon)).normalize();
  }

  function findDrySpawnNormal(latitude, longitude) {
    const isDry = normal => {
      const sample = surfaceAtNormal(normal, true);
      return !sample.water && sample.elevation > 0;
    };
    const preferred = normalFromCoordinates(latitude, longitude);
    if (isDry(preferred)) return preferred;
    // Search outward from the primary settlement/spaceport rather than using
    // the old fixed equatorial point. A candidate is accepted only when the
    // actual deterministic terrain field reports positive dry-land elevation.
    for (let radius = 2; radius <= 180; radius += 2) {
      for (let bearing = 0; bearing < 360; bearing += 15) {
        const angle = bearing * Math.PI / 180;
        const lat = clamp(latitude + Math.cos(angle) * radius, -89.5, 89.5);
        const lon = ((longitude + Math.sin(angle) * radius / Math.max(0.15, Math.cos(latitude * Math.PI / 180)) + 540) % 360) - 180;
        const candidate = normalFromCoordinates(lat, lon);
        if (isDry(candidate)) return candidate;
      }
    }
    // Final deterministic global scan handles geography/terrain fields whose
    // legacy settlement locations do not align with the current terrain seed.
    for (let lat = -85; lat <= 85; lat += 5) {
      for (let lon = -180; lon < 180; lon += 5) {
        const candidate = normalFromCoordinates(lat, lon);
        if (isDry(candidate)) return candidate;
      }
    }
    throw new Error('This world has no generated dry-land location for a surface character spawn.');
  }

  function normalFromPosition(position) {
    const normal = position.clone ? position.clone() : new BABYLON.Vector3(position.x, position.y, position.z);
    if (normal.lengthSquared() < .000001) return BABYLON.Axis.Y.clone();
    return normal.normalize();
  }

  function sampleHeight(x, z) {
    const y = Math.sqrt(Math.max(0, planetRadius * planetRadius - x * x - z * z));
    const normal = new BABYLON.Vector3(x, y, z).normalize();
    return planetRadius + surfaceAtNormal(normal).elevation;
  }

  function fromLocalCoordinates(x, z) {
    const distance = Math.hypot(Number(x) || 0, Number(z) || 0);
    const angle = distance / planetRadius;
    // Keep the local multiplayer coordinate frame consistent with the global
    // map: +X is 0° latitude / 0° longitude, +Z is east, and +Y is north.
    const tangent = distance > .000001
      ? new BABYLON.Vector3(0, Number(z) / distance, Number(x) / distance)
      : BABYLON.Vector3.Zero();
    const radial = BABYLON.Axis.X.scale(Math.cos(angle)).add(tangent.scale(Math.sin(angle))).normalize();
    return radial.scale(planetRadius + surfaceAtNormal(radial).elevation);
  }

  function toLocalCoordinates(position) {
    const normal = normalFromPosition(position);
    const angle = Math.acos(clamp(normal.x, -1, 1));
    const tangentLength = Math.sin(angle);
    if (tangentLength < 0.000001) return { x: 0, z: 0 };
    const distance = angle * planetRadius;
    return { x: normal.z / tangentLength * distance, z: normal.y / tangentLength * distance };
  }

  function getSurfacePosition(position) {
    const normal = normalFromPosition(position);
    const coordinates = getCoordinates(normal);
    const dryNormal = findDrySpawnNormal(coordinates.latitude, coordinates.longitude);
    const sample = surfaceAtNormal(dryNormal, true);
    return dryNormal.scale(planetRadius + sample.elevation + 0.02);
  }

  function moveOnSurface(position, direction, distance) {
    const radial = normalFromPosition(position);
    const tangent = direction.clone().subtract(radial.scale(BABYLON.Vector3.Dot(direction, radial)));
    if (tangent.lengthSquared() < .000001 || distance <= 0) return position.clone();
    tangent.normalize();
    const angle = distance / planetRadius;
    const moved = radial.scale(Math.cos(angle)).add(tangent.scale(Math.sin(angle))).normalize();
    return moved.scale(planetRadius + surfaceAtNormal(moved).elevation);
  }

  function getSurfaceNormal(position) { return normalFromPosition(position); }

  // Positive east is the derivative of the spherical coordinate frame with
  // respect to longitude. This avoids relying on an implicit cross-product
  // convention at spawn and keeps travel aligned with the displayed longitude.
  function getEastTangent(position) {
    const normal = normalFromPosition(position);
    let east = new BABYLON.Vector3(-normal.z, 0, normal.x);
    if (east.lengthSquared() < .000001) east = BABYLON.Axis.Z.clone();
    return east.normalize();
  }

  // North is the increasing-latitude tangent. Together these two unit vectors
  // convert a world-space tangent into the server's local map axes: X=east, Z=north.
  function getNorthTangent(position) {
    const normal = normalFromPosition(position);
    const east = getEastTangent(position);
    return BABYLON.Vector3.Cross(east, normal).normalize();
  }

  function getLocalMovementComponents(position, direction) {
    const east = getEastTangent(position);
    const north = getNorthTangent(position);
    return {
      east: BABYLON.Vector3.Dot(direction, east),
      north: BABYLON.Vector3.Dot(direction, north)
    };
  }

  function sampleAtCoordinates(latitude, longitude) {
    const lat = Number(latitude) * Math.PI / 180;
    const lon = Number(longitude) * Math.PI / 180;
    const normal = new BABYLON.Vector3(Math.cos(lat) * Math.cos(lon), Math.sin(lat), Math.cos(lat) * Math.sin(lon));
    return surfaceAtNormal(normal);
  }

  function getCoordinates(position) {
    const normal = normalFromPosition(position);
    const latitude = Math.asin(clamp(normal.y, -1, 1)) * 180 / Math.PI;
    const longitude = Math.atan2(normal.z, normal.x) * 180 / Math.PI;
    return { latitude, longitude };
  }

  function getHexAt(x, z, position = null) {
    if (!planetaryGeography?.map?.hexes?.length) return spawnHex;
    // Legacy hexes remain useful as a temporary local reference overlay only.
    const coordinates = position ? getCoordinates(position) : { latitude: 90 - (z / planetRadius) * 180, longitude: (x / planetRadius) * 180 };
    const q = clamp(Math.floor((coordinates.longitude + 180) / 360 * Number(planetaryGeography.map.columns || 20)), 0, Number(planetaryGeography.map.columns || 20) - 1);
    const r = clamp(Math.floor((90 - coordinates.latitude) / 180 * Number(planetaryGeography.map.rows || 16)), 0, Number(planetaryGeography.map.rows || 16) - 1);
    return planetaryGeography.map.hexes.find(hex => Number(hex.q) === q && Number(hex.r) === r) || spawnHex;
  }

  function updateLocalPatch(position, force = false) {
    if (!localPatchMesh || !localPatchOffsets || !position) return;
    const center = normalFromPosition(position);
    // Keep only the newest requested center. Sampling a complete 103k-vertex
    // patch in one frame caused the visible hitch during fast travel.
    if (!force && localPatchCenter && BABYLON.Vector3.Dot(center, localPatchCenter) > Math.cos(6 / planetRadius)) return;
    localPatchRequestCenter = center;
    if (localPatchBuild || localPatchBuildScheduled) return;
    localPatchBuildScheduled = true;
    requestAnimationFrame(() => {
      localPatchBuildScheduled = false;
      if (!localPatchRequestCenter || !localPatchMesh || !localPatchOffsets) return;
      const buildCenter = localPatchRequestCenter.clone();
      localPatchRequestCenter = null;
      const east = getEastTangent(buildCenter);
      const north = getNorthTangent(buildCenter);
      const positions = new Float32Array(localPatchOffsets.length / 2 * 3);
      const colors = new Float32Array(localPatchOffsets.length / 2 * 4);
      localPatchBuild = { center: buildCenter, east, north, positions, colors, cursor: 0 };
      const processBatch = () => {
        const build = localPatchBuild;
        if (!build || !localPatchMesh) { localPatchBuild = null; return; }
        const end = Math.min(localPatchOffsets.length / 2, build.cursor + localPatchBatchSize);
        for (let vertex = build.cursor; vertex < end; vertex++) {
          const offsetIndex = vertex * 2;
          const x = localPatchOffsets[offsetIndex];
          const z = localPatchOffsets[offsetIndex + 1];
          const distance = Math.hypot(x, z);
          const angle = distance / planetRadius;
          let direction = build.center;
          if (distance > 0.000001) {
            const tangent = build.east.scale(x / distance).add(build.north.scale(z / distance));
            direction = build.center.scale(Math.cos(angle)).add(tangent.scale(Math.sin(angle))).normalize();
          }
          const edgeFade = 1 - smooth(clamp(distance / (localPatchSizeKm / 2), 0, 1));
          const surface = surfaceAtNormal(direction, true, edgeFade);
          const radius = planetRadius + (surface.water ? 0.025 : surface.elevation + 0.012);
          const positionOffset = vertex * 3;
          const colorOffset = vertex * 4;
          build.positions[positionOffset] = direction.x * radius;
          build.positions[positionOffset + 1] = direction.y * radius;
          build.positions[positionOffset + 2] = direction.z * radius;
          build.colors[colorOffset] = surface.color[0];
          build.colors[colorOffset + 1] = surface.color[1];
          build.colors[colorOffset + 2] = surface.color[2];
          build.colors[colorOffset + 3] = 1;
        }
        build.cursor = end;
        if (end < localPatchOffsets.length / 2) {
          requestAnimationFrame(processBatch);
          return;
        }
        // Keep the old patch visible while the replacement is prepared, then
        // commit all vertex buffers together to avoid partially updated terrain.
        localPatchMesh.updateVerticesData(BABYLON.VertexBuffer.PositionKind, build.positions);
        localPatchMesh.setVerticesData(BABYLON.VertexBuffer.ColorKind, build.colors, true);
        const normals = [];
        BABYLON.VertexData.ComputeNormals(build.positions, localPatchMesh.getIndices(), normals);
        localPatchMesh.updateVerticesData(BABYLON.VertexBuffer.NormalKind, normals);
        localPatchMesh.refreshBoundingInfo(true);
        localPatchCenter = build.center;
        localPatchBuild = null;
        if (localPatchRequestCenter && BABYLON.Vector3.Dot(localPatchRequestCenter, localPatchCenter) <= Math.cos(6 / planetRadius)) {
          updateLocalPatch(localPatchRequestCenter, true);
        } else {
          localPatchRequestCenter = null;
        }
      };
      requestAnimationFrame(processBatch);
    });
  }

  function createLocalPatch(scene, centerPosition) {
    const patch = BABYLON.MeshBuilder.CreateGround('core-local-terrain-relief', {
      width: localPatchSizeKm,
      height: localPatchSizeKm,
      subdivisions: localPatchSegments,
      updatable: true
    }, scene);
    const positions = patch.getVerticesData(BABYLON.VertexBuffer.PositionKind);
    localPatchOffsets = new Float32Array((positions.length / 3) * 2);
    for (let i = 0, j = 0; i < positions.length; i += 3, j += 2) {
      localPatchOffsets[j] = positions[i];
      localPatchOffsets[j + 1] = positions[i + 2];
    }
    patch.material = surfaceMesh.material;
    patch.isPickable = false;
    patch.alwaysSelectAsActiveMesh = true;
    localPatchMesh = patch;
    updateLocalPatch(centerPosition, true);
  }

  function createWaterSurface(scene) {
    const segments = 128;
    const water = BABYLON.MeshBuilder.CreateSphere('core-low-poly-ocean', {
      diameter: planetRadius * 2 + 0.02, segments, updatable: true
    }, scene);
    const positions = water.getVerticesData(BABYLON.VertexBuffer.PositionKind);
    const colors = [];
    for (let index = 0; index < positions.length; index += 3) {
      const normal = new BABYLON.Vector3(positions[index], positions[index + 1], positions[index + 2]).normalize();
      const sample = surfaceAtNormal(normal, false);
      const color = sample.water ? sample.color : [0.008, 0.025, 0.065];
      colors.push(color[0], color[1], color[2], 1);
    }
    water.setVerticesData(BABYLON.VertexBuffer.ColorKind, colors);
    const material = new BABYLON.StandardMaterial('core-low-poly-ocean-material', scene);
    material.diffuseColor = BABYLON.Color3.White();
    material.specularColor = new BABYLON.Color3(0.38, 0.52, 0.68);
    material.specularPower = 72;
    material.emissiveColor = new BABYLON.Color3(0.006, 0.025, 0.035);
    material.useVertexColors = true;
    material.vertexColor = true;
    material.backFaceCulling = false;
    material.freeze();
    water.material = material;
    water.isPickable = false;
    water.alwaysSelectAsActiveMesh = true;
    waterBasePositions = positions.slice();
    waterMesh = water;
  }

  function updateWater(delta) {
    if (!waterMesh || !waterBasePositions) return;
    waterUpdateAccumulator += Math.max(0, Math.min(0.05, Number(delta) || 0));
    // Water animation is intentionally updated at a modest cadence. This
    // avoids per-frame vertex work while retaining visibly slow facet motion.
    if (waterUpdateAccumulator < 0.18) return;
    waterPhase += waterUpdateAccumulator;
    waterUpdateAccumulator = 0;
    const positions = waterBasePositions.slice();
    for (let index = 0; index < positions.length; index += 3) {
      const x = waterBasePositions[index], y = waterBasePositions[index + 1], z = waterBasePositions[index + 2];
      const inverseLength = 1 / Math.sqrt(x * x + y * y + z * z);
      const nx = x * inverseLength, ny = y * inverseLength, nz = z * inverseLength;
      const wave = Math.sin(nx * 9.0 + nz * 7.0 + waterPhase * 0.8)
        * Math.sin(ny * 8.0 - nz * 5.0 + waterPhase * 0.55) * 0.018;
      const radius = planetRadius + 0.01 + wave;
      positions[index] = nx * radius;
      positions[index + 1] = ny * radius;
      positions[index + 2] = nz * radius;
    }
    // Wave displacement stays inside the precomputed ocean bounds; skipping a
    // per-update bounds rebuild avoids unnecessary CPU work.
    waterMesh.updateVerticesData(BABYLON.VertexBuffer.PositionKind, positions, false, false);
  }

  function create(scene) {
    if (!planetaryGeography) throw new Error('Planetary geography must be loaded before terrain construction.');
    // A higher tessellation is required for the generated elevation field to
    // become visible as geometry rather than only as vertex coloration.
    // The planet-wide mesh is a distant fallback; the high-resolution local
    // patch supplies close terrain. Avoid a needlessly dense 512-segment sphere.
    const segments = 128;
    const mesh = BABYLON.MeshBuilder.CreateSphere('core-planetary-terrain', {
      diameter: planetRadius * 2, segments, updatable: true
    }, scene);
    const positions = mesh.getVerticesData(BABYLON.VertexBuffer.PositionKind);
    const colors = [];
    const normals = [];
    for (let i = 0; i < positions.length; i += 3) {
      const normal = new BABYLON.Vector3(positions[i], positions[i + 1], positions[i + 2]).normalize();
      const surface = surfaceAtNormal(normal, false);
      const radius = planetRadius + surface.elevation;
      positions[i] = normal.x * radius;
      positions[i + 1] = normal.y * radius;
      positions[i + 2] = normal.z * radius;
      colors.push(surface.color[0], surface.color[1], surface.color[2], 1);
    }
    mesh.updateVerticesData(BABYLON.VertexBuffer.PositionKind, positions);
    // Refresh bounds after CPU-side vertex displacement so Babylon culling and
    // camera/frustum calculations use the actual elevated terrain envelope.
    mesh.refreshBoundingInfo(true);
    mesh.setVerticesData(BABYLON.VertexBuffer.ColorKind, colors);
    BABYLON.VertexData.ComputeNormals(positions, mesh.getIndices(), normals);
    mesh.setVerticesData(BABYLON.VertexBuffer.NormalKind, normals);
    const material = new BABYLON.StandardMaterial('core-planetary-terrain-material', scene);
    material.diffuseColor = BABYLON.Color3.White();
    material.specularColor = new BABYLON.Color3(.025, .035, .045);
    material.specularPower = 24;
    material.vertexColor = true;
    material.useVertexColors = true;
    mesh.material = material;
    mesh.isPickable = false;
    mesh.freezeWorldMatrix();
    surfaceMesh = mesh;
    createWaterSurface(scene);

    const spawnSurface = surfaceAtNormal(spawnNormal, true);
    if (spawnSurface.water || spawnSurface.elevation <= 0) throw new Error('The selected character spawn is not on dry land.');
    const spawn = spawnNormal.scale(planetRadius + spawnSurface.elevation);
    createLocalPatch(scene, spawn);
    return { mesh, localPatch: localPatchMesh, spawnHeight: spawn.y, centerHex: spawnHex, radius: planetRadius, bounds: null };
  }

  window.CORE_PLANETARY_ENVIRONMENT = {
    load, create, updateLocalPatch, updateWater, sampleHeight, moveOnSurface, fromLocalCoordinates, toLocalCoordinates, getSurfacePosition, getSurfaceNormal, getEastTangent, getNorthTangent, getLocalMovementComponents, getCoordinates, sampleAtCoordinates, getHexAt,
    getAltitude(position) { const normal = normalFromPosition(position); return surfaceAtNormal(normal, true).elevation; },
    get radius() { return planetRadius; },
    get spawnPosition() { const surface = surfaceAtNormal(spawnNormal, true); return spawnNormal.scale(planetRadius + surface.elevation); },
    get spawnHex() { return spawnHex; },
    get geography() { return planetaryGeography; },
    get terrain() { return planetaryTerrain; },
    get climate() { return planetaryClimate; }
  };
})();
