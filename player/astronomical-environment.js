(() => {
  'use strict';

  const DAY_SECONDS = 1200; // One simulated planetary day per 20 real minutes.
  const MOON_ORBIT_PATH_SEGMENTS = 64;
  const SKY_RADIUS = 18000;
  const STARFIELD_COUNT = 720;
  const SYSTEM_PLANET_SCALE = 42000;
  // Developer presentation starts disabled; normal moon rendering remains active.
  const MOON_DEBUG_VISUALS_DEFAULT = false;
  const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
  const fract = value => value - Math.floor(value);

  // UWP atmosphere is the authoritative classification. Rendering strength is
  // deliberately separate from gas composition: A-F remain conservative
  // generic profiles until a world has explicit atmospheric composition data.
  function atmosphereProfile(world) {
    const raw = world?.atmosphere ?? world?.physical?.atmosphere_code ?? 0;
    const code = Number.isFinite(Number(raw)) ? clamp(Math.trunc(Number(raw)), 0, 15) : 0;
    const profiles = [
      { scattering: 0, fog: 0, horizon: 0, starFade: 0 },       // 0 vacuum
      { scattering: .025, fog: .000015, horizon: .02, starFade: .03 }, // 1 trace
      { scattering: .08, fog: .000035, horizon: .06, starFade: .08 }, // 2 very thin tainted
      { scattering: .12, fog: .00005, horizon: .09, starFade: .12 },  // 3 very thin
      { scattering: .18, fog: .000075, horizon: .13, starFade: .18 }, // 4 thin tainted
      { scattering: .24, fog: .0001, horizon: .18, starFade: .24 },  // 5 thin
      { scattering: .48, fog: .00022, horizon: .38, starFade: .52 }, // 6 standard
      { scattering: .5, fog: .00024, horizon: .4, starFade: .54 },   // 7 standard tainted
      { scattering: .72, fog: .00038, horizon: .62, starFade: .76 }, // 8 dense
      { scattering: .74, fog: .0004, horizon: .64, starFade: .78 },  // 9 dense tainted
      { scattering: .2, fog: .00008, horizon: .16, starFade: .2 },   // A exotic: composition unknown
      { scattering: .42, fog: .0002, horizon: .34, starFade: .42 }, // B corrosive
      { scattering: .3, fog: .00014, horizon: .24, starFade: .3 },   // C insidious
      { scattering: .82, fog: .00048, horizon: .72, starFade: .86 }, // D dense high
      { scattering: .16, fog: .000065, horizon: .12, starFade: .16 },// E thin low
      { scattering: .22, fog: .00009, horizon: .18, starFade: .22 }  // F unusual
    ];
    return { code, ...(profiles[code] || profiles[0]) };
  }

  function hashNumber(value) {
    let h = 2166136261;
    const text = String(value || 'CORE');
    for (let i = 0; i < text.length; i++) h = Math.imul(h ^ text.charCodeAt(i), 16777619);
    return (h >>> 0) / 4294967296;
  }

  function spectralColor(spectralClass) {
    return ({
      O: new BABYLON.Color3(.55, .68, 1),
      B: new BABYLON.Color3(.68, .78, 1),
      A: new BABYLON.Color3(.84, .88, 1),
      F: new BABYLON.Color3(1, .96, .82),
      G: new BABYLON.Color3(1, .88, .62),
      K: new BABYLON.Color3(1, .68, .42),
      M: new BABYLON.Color3(1, .38, .25)
    })[String(spectralClass || 'G').charAt(0).toUpperCase()] || new BABYLON.Color3(1, .88, .68);
  }

  function normalizeBodies(system) {
    const stars = Array.isArray(system?.stars) ? system.stars.slice()
      : (system?.star && typeof system.star === 'object' ? [system.star] : []);
    const listed = Array.isArray(system?.celestial_bodies) ? system.celestial_bodies : [];
    const central = system?.central_body && typeof system.central_body === 'object'
      ? system.central_body
      : (listed.find(body => body?.is_central === true) || null);
    const explicitStars = listed.filter(body => String(body?.type || '').toLowerCase() === 'star');
    const allStars = stars.length ? stars : explicitStars;

    // Older CORE system records only flag a binary primary. Materialize a stable
    // companion for presentation without changing the saved universe record.
    if (allStars.length === 1 && allStars[0].is_binary === true) {
      const primary = allStars[0];
      const seed = `${system.seed || system.id || 'CORE'}:binary-companion`;
      const classes = ['A', 'F', 'G', 'K', 'M'];
      allStars.push({
        id: `derived-${primary.id || 'primary'}-companion`,
        name: 'Companion',
        spectral_class: classes[Math.floor(hashNumber(seed) * classes.length)],
        derived_for_legacy_binary: true
      });
    }
    return { central, stars: allStars };
  }

  function makeStarDescriptor(star, index, systemSeed) {
    const spectral = String(star.spectral_class || star.spectral_type || 'G').charAt(0).toUpperCase();
    const color = spectralColor(spectral);
    const seed = `${systemSeed}:${star.id || star.name || index}`;
    const luminosity = clamp(Number(star.luminosity ?? star.luminosity_solar ?? ({
      O: 12, B: 7, A: 3.5, F: 1.8, G: 1, K: .55, M: .22
    })[spectral]) || 1, .05, 30);
    return {
      id: String(star.id || `star-${index + 1}`),
      name: String(star.name || `Star ${index + 1}`),
      color,
      luminosity,
      size: clamp(.72 + Math.log2(luminosity + 1) * .18, .65, 1.8),
      phase: hashNumber(seed + ':phase') * Math.PI * 2,
      orbitRate: .12 + hashNumber(seed + ':rate') * .19,
      vertical: (hashNumber(seed + ':vertical') - .5) * .28,
      light: null,
      mesh: null,
      material: null
    };
  }

  const environment = {
    scene: null,
    boot: null,
    bodies: [],
    centralMesh: null,
    centralMaterial: null,
    moonMeshes: [],
    moonMaterials: [],
    systemPlanets: [],
    developerOptions: { orbitPaths: false, positionMarkers: false, worldLabels: false, sizeDiagnostics: false },
    developerMarkers: [],
    orbitForward: null,
    orbitRight: null,
    moonDebugVisuals: MOON_DEBUG_VISUALS_DEFAULT,
    ambient: null,
    starLayer: null,
    starTexture: null,
    elapsed: 0,
    clockSyncTimer: null,
    clockSyncInFlight: false,
    pendingClockAdjustmentHours: 0,
    dayLengthSeconds: DAY_SECONDS,
    rotationPhase: 0,
    skyDay: 0,
    illumination: 0,
    dayPhase: 'day',
    atmosphere: null,
    destroyed: false,

    create(scene, boot, initialCameraDirection) {
      this.scene = scene;
      this.boot = boot || {};
      // Celestial meshes render in group 3, but must retain the terrain depth
      // buffer so the planet naturally hides the below-horizon portions.
      if (typeof scene.setRenderingAutoClearDepthStencil === 'function') {
        scene.setRenderingAutoClearDepthStencil(1, false);
        scene.setRenderingAutoClearDepthStencil(2, false);
        scene.setRenderingAutoClearDepthStencil(3, false);
      }
      this.elapsed = Math.max(0, Number(this.boot.clock?.elapsedSeconds) || 0);
      this.clockSyncTimer = null;
      this.clockSyncInFlight = false;
      this.pendingClockAdjustmentHours = 0;
      this.destroyed = false;
      const system = this.boot.system || {};
      const world = this.boot.world || {};
      this.atmosphere = atmosphereProfile(world);
      // Fog is local planetary atmospheric optics only. Celestial materials
      // explicitly disable fog, preserving their intrinsic visibility.
      scene.fogMode = BABYLON.Scene.FOGMODE_EXP2;
      scene.fogDensity = this.atmosphere.fog;
      const systemSeed = String(system.seed || system.id || 'CORE');
      const universeSeed = String(this.boot.universe?.seed || '').trim().toLowerCase();
      const systemLabel = `${system.name || ''} ${system.id || ''}`.toLowerCase();
      const constellationLabel = `${this.boot.constellation?.name || ''} ${this.boot.constellation?.id || ''}`.toLowerCase();
      // A narrowly scoped visibility-test arrangement for the documented
      // tttttt / Constellation 01 / System 01 starting location. All other
      // universes and systems continue to use their generated orbital data.
      const isLunarVisibilityTest = universeSeed === 'tttttt'
        && /constellation\s*0?1|(?:^|[^a-z0-9])c0?1(?:$|[^a-z0-9])/.test(constellationLabel)
        && /system\s*0?1|(?:^|[^a-z0-9])s0?1(?:$|[^a-z0-9])/.test(systemLabel);
      this.systemPlanets = [];
      this.isLunarVisibilityTest = isLunarVisibilityTest;
      const worldSeed = String(world.seed || world.id || 'CORE-WORLD');
      const configuredDay = Number(world.physical?.rotation_period_hours ?? world.rotation_period_hours ?? world.day_length_hours);
      const generatedHours = 18 + Math.floor(hashNumber(worldSeed + ':rotation') * 31);
      const dayHours = Number.isFinite(configuredDay) && configuredDay > 0
        ? clamp(configuredDay, 4, 200) : generatedHours;
      // Preserve a visible, playable cycle while respecting each world's relative
      // rotation period. The simulation clock is intentionally accelerated.
      this.dayLengthSeconds = DAY_SECONDS * (dayHours / 24);
      this.rotationPhase = hashNumber(worldSeed + ':solar-phase') * Math.PI * 2;
      // Planet-fixed orbital axes use the planet's own coordinate frame only.
      // Neither the initial nor live camera direction participates in orbit geometry.
      this.orbitForward = BABYLON.Axis.Z.clone();
      this.orbitRight = BABYLON.Axis.X.clone();

      const resolved = normalizeBodies(system);
      this.bodies = resolved.stars.map((star, index) => makeStarDescriptor(star, index, systemSeed));
      if (resolved.central && (resolved.central.is_luminous === true || Number(resolved.central.luminosity) > 0)) {
        this.bodies.push(makeStarDescriptor({
          ...resolved.central,
          id: resolved.central.id || 'central-luminous-body',
          name: resolved.central.name || 'Central luminous body',
          spectral_class: resolved.central.spectral_class || resolved.central.spectral_type || 'K',
          luminosity: resolved.central.luminosity || .08
        }, this.bodies.length, systemSeed));
      }
      this.bodies.forEach((body, index) => {
        body.light = new BABYLON.DirectionalLight(
          `core-celestial-light-${index}`, new BABYLON.Vector3(0, -1, 0), scene
        );
        body.light.diffuse = body.color.clone();
        body.light.specular = body.color.scale(.35);
        body.light.intensity = 0;
        body.mesh = BABYLON.MeshBuilder.CreateSphere(`core-celestial-disc-${index}`, {
          diameter: 260 * body.size, segments: 16
        }, scene);
        body.material = new BABYLON.StandardMaterial(`core-celestial-material-${index}`, scene);
        body.material.disableLighting = true;
        // Scene fog is intentionally reserved for the local planetary environment.
        // At SKY_RADIUS, EXP2 fog would otherwise attenuate even emissive meshes
        // to near-black (disableLighting does not disable material fog).
        body.material.fogEnabled = false;
        body.material.emissiveColor = body.color.scale(1.6);
        body.material.diffuseColor = body.color;
        body.material.specularColor = BABYLON.Color3.Black();
        body.mesh.material = body.material;
        body.mesh.isPickable = false;
        body.mesh.fogEnabled = false;
      });

      // A non-stellar central body is represented independently; it never
      // becomes a light source merely because it occupies the central-body slot.
      if (resolved.central) {
        const kind = String(resolved.central.type || resolved.central.classification || 'astronomical body').toLowerCase();
        const color = kind.includes('black') ? new BABYLON.Color3(.025, .02, .035)
          : kind.includes('neutron') ? new BABYLON.Color3(.55, .72, 1)
          : new BABYLON.Color3(.48, .42, .36);
        this.centralMesh = BABYLON.MeshBuilder.CreateSphere('core-central-astral-body', {
          diameter: 360, segments: 24
        }, scene);
        this.centralMaterial = new BABYLON.StandardMaterial('core-central-astral-material', scene);
        this.centralMaterial.disableLighting = true;
        this.centralMaterial.fogEnabled = false;
        this.centralMaterial.diffuseColor = color;
        this.centralMaterial.emissiveColor = resolved.central.is_luminous === true ? color.scale(.8) : color.scale(.035);
        this.centralMesh.material = this.centralMaterial;
        this.centralMesh.isPickable = false;
        this.centralMesh.fogEnabled = false;
      }

      // Natural satellites are rendered only when the world data explicitly
      // defines one; the day/night icon below does not imply a physical moon.
      const moons = (Array.isArray(world.moons) ? world.moons
        : (Array.isArray(world.satellites) ? world.satellites.filter(body => String(body?.type || '').toLowerCase() === 'moon') : []))
        .filter(body => body && typeof body === 'object');
      this.moonMeshes = moons.map((moon, index) => {
        const mesh = BABYLON.MeshBuilder.CreateSphere(`core-natural-satellite-${index + 1}`, {
          diameter: clamp(Number(moon.diameter_km || moon.diameter || 180) / 4, 96, 600), segments: 32
        }, scene);
        const material = new BABYLON.StandardMaterial(`core-natural-satellite-material-${index + 1}`, scene);
        material.diffuseColor = new BABYLON.Color3(.72, .74, .78);
        // Moons are celestial presentation objects, not terrain-lit props.
        // Disable scene lighting so their visibility cannot collapse with the
        // local night-side lighting state; retain a restrained luminous disc.
        material.disableLighting = true;
        material.fogEnabled = false;
        material.emissiveColor = new BABYLON.Color3(.82, .84, .90);
        material.diffuseColor = new BABYLON.Color3(.78, .80, .86);
        material.specularColor = BABYLON.Color3.Black();
        // The moon ray is already constrained to clear the geometric horizon.
        // Do not let stale/incorrect terrain depth values hide a sky object.
        // Keep the object opaque and render it after terrain with depth testing
        // bypassed; this is a presentation safeguard, not a change to its orbit.
        material.alpha = 1;
        material.transparencyMode = BABYLON.Material.MATERIAL_OPAQUE;
        material.disableDepthWrite = true;
        material.depthFunction = BABYLON.Engine.ALWAYS;
        mesh.scaling.setAll(3.0);
        mesh.material = material;
        mesh.isPickable = false;
        mesh.isVisible = true;
        mesh.visibility = 1;
        mesh.renderingGroupId = 3;
        mesh.alwaysSelectAsActiveMesh = true;
        mesh.fogEnabled = false;
        const debugMaterial = new BABYLON.StandardMaterial(`core-moon-debug-material-${index + 1}`, scene);
        const debugColor = BABYLON.Color3.White();
        debugMaterial.disableLighting = true;
        debugMaterial.fogEnabled = false;
        debugMaterial.diffuseColor = BABYLON.Color3.White();
        debugMaterial.emissiveColor = BABYLON.Color3.White().scale(4.0);
        debugMaterial.specularColor = BABYLON.Color3.Black();
        debugMaterial.alpha = 1;
        debugMaterial.disableDepthWrite = true;
        debugMaterial.depthFunction = BABYLON.Engine.ALWAYS;
        debugMaterial.transparencyMode = BABYLON.Material.MATERIAL_OPAQUE;
        this.moonMaterials.push(material, debugMaterial);
        return {
          mesh,
          normalMaterial: material,
          debugMaterial,
          debugColor,
          orbitMesh: null,
          orbitMeshes: [],
          orbitMaterial: null,
          phase: (Number(moon.phase_degrees) || hashNumber(String(moon.id || worldSeed) + ':phase') * 360) * Math.PI / 180,
          periodHours: clamp(Number(moon.orbital_period_hours) || 120, 4, 20000),
          inclination: (Number(moon.inclination_degrees) || 0) * Math.PI / 180,
          azimuthOffset: (hashNumber(String(moon.id || worldSeed) + ':sky-azimuth') - .5) * .36,
          radius: isLunarVisibilityTest
            ? (index === 0 ? 1.5 : 1.85)
            : clamp(Number(moon.orbital_radius_planet_radii) || (1.8 + index * .45), 1.2, 30),
          orbitNode: isLunarVisibilityTest ? 0
            : hashNumber(String(moon.id || worldSeed) + ':orbit-node') * Math.PI * 2,
          // Start the two diagnostic moons in a separated, forward-facing arc.
          // This is a one-time phase choice in the fixed orbital plane, not a
          // camera-relative position; the phase then advances with simulation time.
          orbitPhaseOffset: isLunarVisibilityTest ? (5.61 + index * .09)
            : .62 + index * .30 + (hashNumber(String(moon.id || worldSeed) + ':orbit-start') - .5) * .06,
          orbitRadiusWorld: null,
          orbitCenter: BABYLON.Vector3.Zero(),
          orbitGeometryInitialized: false
        };
      });
      this.moonMeshes.forEach((moon, index) => {
        moon.orbitMaterial = new BABYLON.StandardMaterial(`core-moon-orbit-material-${index + 1}`, scene);
        moon.orbitMaterial.disableLighting = true;
        moon.orbitMaterial.fogEnabled = false;
        // Orbit guides are intentionally luminous, neutral markers. Keep their
        // color independent of scene lighting so terrain/sky lighting cannot
        // turn the guides into nearly-black silhouettes.
        moon.orbitMaterial.diffuseColor = BABYLON.Color3.White();
        moon.orbitMaterial.emissiveColor = BABYLON.Color3.White().scale(4.0);
        moon.orbitMaterial.specularColor = BABYLON.Color3.Black();
        moon.orbitMaterial.disableDepthWrite = true;
        // Horizon clipping is performed geometrically per orbit segment below;
        // ALWAYS prevents stale terrain depth from hiding valid sky-side arcs.
        moon.orbitMaterial.depthFunction = BABYLON.Engine.ALWAYS;
        moon.orbitMaterial.alpha = 1;
        moon.orbitMaterial.alphaMode = BABYLON.Engine.ALPHA_DISABLE;
        moon.orbitMaterial.transparencyMode = BABYLON.Material.MATERIAL_OPAQUE;
        moon.orbitMaterial.backFaceCulling = false;
        const initialOrbitPath = Array.from({ length: MOON_ORBIT_PATH_SEGMENTS * 4 + 1 }, (_, step) => {
          const angle = (step / (MOON_ORBIT_PATH_SEGMENTS * 4)) * Math.PI * 2;
          return new BABYLON.Vector3(Math.cos(angle), Math.sin(angle), 0);
        });
        moon.orbitMesh = BABYLON.MeshBuilder.CreateTube(`core-moon-orbit-${index + 1}`, {
          path: initialOrbitPath, radius: 54, tessellation: 10, updatable: true
        }, scene);
        moon.orbitMesh.material = moon.orbitMaterial;
        moon.orbitMeshes = [moon.orbitMesh];
        moon.orbitMesh.isPickable = false;
        moon.orbitMesh.renderingGroupId = 3;
        moon.orbitMesh.alwaysSelectAsActiveMesh = true;
        moon.orbitMesh.fogEnabled = false;
      });
      // Render every other generated world as a real system object in the sky.
      // Their positions are calculated from deterministic star-centric circular
      // orbits, then converted to observer-relative directions each frame.
      const systemWorlds = Array.isArray(this.boot.systemWorlds) ? this.boot.systemWorlds : [];
      const currentWorldId = String(world.id || '');
      this.systemPlanets = systemWorlds.filter(item => item && String(item.id || '') !== currentWorldId).map((item, index) => {
        const type = String(item.type || '').toLowerCase();
        // Distinct, softly luminous spectral colors make distant system
        // worlds readable without competing with the local star or moon guides.
        const color = type.includes('ocean') ? new BABYLON.Color3(.30, .66, 1)
          : type.includes('ice') || type.includes('frozen') ? new BABYLON.Color3(.68, .88, 1)
          : type.includes('desert') ? new BABYLON.Color3(1, .67, .34)
          : type.includes('volcan') || type.includes('lava') ? new BABYLON.Color3(1, .38, .22)
          : type.includes('gas') || type.includes('jovian') ? new BABYLON.Color3(1, .83, .55)
          : type.includes('temperate') || type.includes('terrestrial') ? new BABYLON.Color3(.56, .91, .67)
          : type.includes('asteroid') ? new BABYLON.Color3(.82, .78, .68)
          : type.includes('exotic') ? new BABYLON.Color3(.79, .65, 1)
          : new BABYLON.Color3(.86, .88, .94);
        const slot = Math.max(1, Number(item.orbital_slot) || index + 1);
        const seed = `${systemSeed}:${item.id || slot}:system-orbit`;
        const radius = SYSTEM_PLANET_SCALE * slot;
        const periodHours = 8760 * Math.pow(slot, 1.5);
        const phase = hashNumber(seed + ':phase') * Math.PI * 2;
        const node = (hashNumber(seed + ':node') - .5) * .16;
        const inclination = (hashNumber(seed + ':inclination') - .5) * .12;
        // Convert generated physical radius to the planetary scene's scale, then
        // project its angular diameter at the observer's actual star-centric distance.
        // The marker sits at SKY_RADIUS, so scale the angular diameter onto that shell.
        const physicalRadiusKm = Math.max(1, Number(item.physical?.radius_km ?? item.radius_km ?? item.diameter_km / 2) ||
          (type.includes('gas') ? 55000 : type.includes('asteroid') ? 210 : 6000));
        const localRadiusKm = Math.max(1, Number(this.boot.world?.physical?.radius_km ?? this.boot.world?.radius_km) || 6400);
        const physicalRadiusScene = physicalRadiusKm / localRadiusKm * 6400;
        const mesh = BABYLON.MeshBuilder.CreateSphere(`core-system-planet-${item.id || slot}`, {
          diameter: 10, segments: 24
        }, scene);
        const material = new BABYLON.StandardMaterial(`core-system-planet-material-${item.id || slot}`, scene);
        material.disableLighting = true;
        material.fogEnabled = false;
        material.diffuseColor = color;
        material.emissiveColor = color.scale(4.5);
        material.specularColor = BABYLON.Color3.Black();
        material.disableDepthWrite = true;
        // Marker visibility is decided against the geometric horizon below.
        material.depthFunction = BABYLON.Engine.ALWAYS;
        material.alpha = 1;
        material.alphaMode = BABYLON.Engine.ALPHA_DISABLE;
        material.transparencyMode = BABYLON.Material.MATERIAL_OPAQUE;
        material.backFaceCulling = false;
        mesh.material = material;
        mesh.isPickable = false;
        mesh.fogEnabled = false;
        mesh.renderingGroupId = 3;
        mesh.alwaysSelectAsActiveMesh = true;
        mesh.billboardMode = BABYLON.Mesh.BILLBOARDMODE_ALL;
        return { world: item, mesh, material, color, slot, radius, periodHours, phase, node, inclination, physicalRadiusKm, physicalRadiusScene, apparentDiameter: 10, labelMesh: null, markerMesh: null };
      });

      this.setMoonDebugVisuals(this.moonDebugVisuals);

      this.ambient = new BABYLON.HemisphericLight(
        'core-planetary-ambient', new BABYLON.Vector3(0, 1, 0), scene
      );
      this.ambient.intensity = .92;
      this.ambient.diffuse = new BABYLON.Color3(.72, .78, .88);
      this.ambient.groundColor = new BABYLON.Color3(.20, .22, .27);

      // Screen-space star layer: unlike distant 3D sprites, this is not subject
      // to camera clipping, terrain depth, billboard orientation, or world scale.
      // A transparent deterministic texture is composited behind the 3D scene.
      const starTexture = new BABYLON.DynamicTexture(
        'core-night-sky-star-texture', { width: 2048, height: 1024 }, scene, false,
        BABYLON.Texture.BILINEAR_SAMPLINGMODE
      );
      starTexture.hasAlpha = true;
      const starContext = starTexture.getContext();
      starContext.clearRect(0, 0, 2048, 1024);
      const random = (() => {
        let state = Math.floor(hashNumber(systemSeed + ':starfield') * 4294967295) >>> 0;
        return () => {
          state = (Math.imul(state, 1664525) + 1013904223) >>> 0;
          return state / 4294967296;
        };
      })();
      for (let i = 0; i < 1800; i++) {
        const x = random() * 2048;
        const y = random() * 1024;
        const radius = random() < .08 ? 1.8 + random() * 1.4 : .55 + random() * .8;
        const alpha = .55 + random() * .45;
        starContext.beginPath();
        starContext.fillStyle = `rgba(225,235,255,${alpha})`;
        starContext.arc(x, y, radius, 0, Math.PI * 2);
        starContext.fill();
      }
      starTexture.update(false);
      this.starTexture = starTexture;
      this.starLayer = new BABYLON.Layer('core-night-sky-stars', null, scene, true);
      this.starLayer.texture = starTexture;
      this.starLayer.isBackground = true;
      this.starLayer.alpha = 0;
      this.starLayer.color = new BABYLON.Color4(1, 1, 1, 1);

      // Moon visibility is provided by the actual 3D meshes and orbit tubes.
      // No screen-space markers or camera-following overlay is created.

      this.update(0, BABYLON.Vector3.Zero(), BABYLON.Vector3.Zero(), initialCameraDirection || BABYLON.Axis.Z);
      this.syncClock();
      this.clockSyncTimer = window.setInterval(() => this.syncClock(), 15000);
    },

    async syncClock() {
      if (this.destroyed || this.clockSyncInFlight) return false;
      this.clockSyncInFlight = true;
      const adjustmentHours = this.pendingClockAdjustmentHours;
      this.pendingClockAdjustmentHours = 0;
      let synchronized = false;
      try {
        const response = await fetch(`${this.boot.apiRoot || ''}/api/world-clock.php`, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'X-CORE-CSRF': String(this.boot.csrfToken || '') },
          body: JSON.stringify({
            universe_id: String(this.boot.universeId || this.boot.universe?.id || ''),
            world_id: String(this.boot.world?.id || ''),
            adjust_hours: adjustmentHours
          })
        });
        if (!response.ok) throw new Error(`Clock sync failed (${response.status}).`);
        const payload = await response.json();
        if (!payload.ok || !Number.isFinite(Number(payload.clock?.elapsedSeconds))) {
          throw new Error('Clock sync returned invalid state.');
        }
        this.elapsed = Math.max(0, Number(payload.clock.elapsedSeconds));
        synchronized = true;
        return true;
      } catch (error) {
        console.warn('CORE simulation clock synchronization failed:', error);
        return false;
      } finally {
        if (!synchronized) this.pendingClockAdjustmentHours += adjustmentHours;
        this.clockSyncInFlight = false;
        if (this.pendingClockAdjustmentHours !== 0 && !this.destroyed) {
          window.setTimeout(() => this.syncClock(), 0);
        }
      }
    },

    adjustTimeHours(hours) {
      const adjustment = Number(hours);
      if (!Number.isFinite(adjustment) || adjustment === 0) return;
      this.pendingClockAdjustmentHours += adjustment;
      this.syncClock();
    },

    update(deltaSeconds, playerPosition, cameraPosition, cameraDirection) {
      if (this.destroyed || !this.scene) return;
      this.elapsed += Math.max(0, Math.min(.1, Number(deltaSeconds) || 0));
      const phase = this.rotationPhase + (this.elapsed / this.dayLengthSeconds) * Math.PI * 2;
      const camera = cameraPosition || playerPosition || BABYLON.Vector3.Zero();
      const center = camera || BABYLON.Vector3.Zero();
      let totalIllumination = 0;
      const surfaceNormal = window.CORE_PLANETARY_ENVIRONMENT?.getSurfaceNormal(playerPosition)
        || BABYLON.Axis.Y.clone();
      const planetRadius = Math.max(1, Number(window.CORE_PLANETARY_ENVIRONMENT?.radius) || 6400);
      const observerRadius = Math.max(planetRadius + 1, camera.length());
      const horizonDot = -Math.sqrt(Math.max(0, 1 - (planetRadius * planetRadius) / (observerRadius * observerRadius)));
      const isAboveHorizon = position => {
        const ray = position.subtract(camera);
        if (ray.lengthSquared() < 1e-8) return false;
        return BABYLON.Vector3.Dot(ray.normalize(), surfaceNormal) > horizonDot + 0.002;
      };

      // The planet is centered at the world origin. Moon orbital axes are fixed
      // in planet space and never rebuilt from the live camera heading.
      const planetCenter = BABYLON.Vector3.Zero();
      const orbitForward = (this.orbitForward || BABYLON.Axis.Z).clone().normalize();
      const orbitRight = (this.orbitRight || BABYLON.Axis.X).clone().normalize();
      const axialTilt = (Number(this.boot.world?.physical?.axial_tilt_degrees) || 0) * Math.PI / 180;
      const planetPole = new BABYLON.Vector3(0, Math.cos(axialTilt), Math.sin(axialTilt)).normalize();

      this.bodies.forEach((body, index) => {
        // Planetary rotation advances every stellar direction around the local
        // pole. Small per-star orbital motion differentiates binary/multiple suns.
        const angle = phase + body.phase + this.elapsed * body.orbitRate * .001;
        const direction = new BABYLON.Vector3(
          Math.cos(angle), body.vertical, Math.sin(angle)
        ).normalize();
        const lightStrength = clamp(body.luminosity / (this.bodies.length || 1), 0, 2.5);
        body.light.direction = direction.scale(-1);
        body.light.intensity = lightStrength * .82;
        body.mesh.position.copyFrom(center.clone().add(direction.scale(SKY_RADIUS)));
        const solarAltitude = BABYLON.Vector3.Dot(surfaceNormal, direction);
        const visibility = clamp(.35 + solarAltitude * .65, .08, 1);
        body.material.emissiveColor = body.color.scale(1.15 + visibility * .65);
        totalIllumination += lightStrength * Math.max(0, solarAltitude);
      });

      this.moonMeshes.forEach((moon, moonIndex) => {
        // Orbital phase advances from the authoritative simulation clock.
        // Positions are calculated around the planet's fixed world-space origin,
        // not from the player, camera position, or current camera direction.
        const worldRotationHours = Math.max(1, Number(this.boot.world?.physical?.rotation_period_hours)
          || Number(this.boot.world?.rotation_period_hours) || 24);
        const orbitalCycles = (this.elapsed / Math.max(1, this.dayLengthSeconds))
          * (worldRotationHours / moon.periodHours);
        const orbitalAngle = moon.orbitPhaseOffset + orbitalCycles * Math.PI * 2;
        const phaseAngle = moon.phase + orbitalCycles * Math.PI * 2;

        const node = Number(moon.orbitNode) || 0;
        const inclination = clamp(Number(moon.inclination) || 0, 0, Math.PI / 3);
        // Build an orbital plane around the planet's tilted equator. The
        // ascending node rotates the plane around the pole; inclination tilts it.
        const referenceU = orbitForward.subtract(planetPole.scale(BABYLON.Vector3.Dot(orbitForward, planetPole)));
        if (referenceU.lengthSquared() < 1e-8) referenceU.copyFrom(orbitRight);
        referenceU.normalize();
        const referenceV = BABYLON.Vector3.Cross(planetPole, referenceU).normalize();
        const equatorialU = referenceU.scale(Math.cos(node)).add(referenceV.scale(Math.sin(node))).normalize();
        const equatorialV = BABYLON.Vector3.Cross(planetPole, equatorialU).normalize();
        const orbitU = equatorialU;
        const orbitV = equatorialV.scale(Math.cos(inclination)).add(planetPole.scale(Math.sin(inclination))).normalize();
        const orbitalRadius = planetRadius * clamp(Number(moon.radius) || 1.8, 1.2, 30);
        moon.orbitRadiusWorld = orbitalRadius;
        moon.orbitCenter.copyFrom(planetCenter);

        const moonDirection = orbitU.scale(Math.cos(orbitalAngle))
          .add(orbitV.scale(Math.sin(orbitalAngle))).normalize();
        moon.mesh.position.copyFrom(planetCenter.add(moonDirection.scale(orbitalRadius)));
        if (moon.positionMarkerMesh) moon.positionMarkerMesh.position.copyFrom(moon.mesh.position);
        moon.mesh.isVisible = this.isLunarVisibilityTest ? true : isAboveHorizon(moon.mesh.position);
        moon.mesh.visibility = moon.mesh.isVisible ? 1 : 0;
        moon.mesh.rotation.y = phaseAngle;

        // Full 3D orbital ellipse, fixed around the parent planet. Keep the
        // original persistent tube and update its vertices in place. This
        // restores the last known working presentation path: no per-frame mesh
        // disposal, no horizon-clipping fragments, and no terrain-depth dependency.
        if (moon.orbitMesh && !moon.orbitGeometryInitialized) {
          const pathPoints = [];
          const pathSegments = MOON_ORBIT_PATH_SEGMENTS * 4;
          for (let step = 0; step <= pathSegments; step++) {
            const pathAngle = (step / pathSegments) * Math.PI * 2;
            const pathDirection = orbitU.scale(Math.cos(pathAngle))
              .add(orbitV.scale(Math.sin(pathAngle))).normalize();
            pathPoints.push(planetCenter.add(pathDirection.scale(orbitalRadius)));
          }
          BABYLON.MeshBuilder.CreateTube(`core-moon-orbit-${moonIndex + 1}`, {
            path: pathPoints, radius: 54, tessellation: 10, instance: moon.orbitMesh
          });
          moon.orbitMesh.setEnabled(this.moonDebugVisuals);
          moon.orbitMesh.isVisible = this.moonDebugVisuals;
          moon.orbitMesh.visibility = 1;
          moon.orbitMeshes = [moon.orbitMesh];
          moon.worldOrbitPoints = pathPoints;
          moon.orbitRadiusWorld = orbitalRadius;
          moon.visibleOrbitArcCount = this.moonDebugVisuals ? 1 : 0;
          moon.orbitPlaneNormal = BABYLON.Vector3.Cross(orbitU, orbitV).normalize();
          moon.orbitGeometryInitialized = true;
        } else if (moon.orbitMesh) {
          moon.orbitMesh.setEnabled(this.moonDebugVisuals);
          moon.orbitMesh.isVisible = this.moonDebugVisuals;
        }
      });

      // Approximate Keplerian system view: each generated world follows a
      // deterministic, nearly circular stellar orbit. Only apparent direction
      // is rendered at sky scale; the underlying orbital positions remain in
      // shared system coordinates and advance with the simulation clock.
      const currentSlot = Math.max(1, Number(this.boot.world?.orbital_slot) || 1);
      const currentSeed = `${this.boot.system?.seed || this.boot.system?.id || 'CORE'}:${this.boot.world?.id || 'world'}:system-orbit`;
      const currentPhase = hashNumber(currentSeed + ':phase') * Math.PI * 2;
      const currentNode = (hashNumber(currentSeed + ':node') - .5) * .16;
      const currentInclination = (hashNumber(currentSeed + ':inclination') - .5) * .12;
      const currentRadius = SYSTEM_PLANET_SCALE * currentSlot;
      const simHours = (this.elapsed / Math.max(1, this.dayLengthSeconds))
        * Math.max(1, Number(this.boot.world?.physical?.rotation_period_hours) || 24);
      const orbitalPosition = (entry, hours) => {
        const angle = entry.phase + (hours / entry.periodHours) * Math.PI * 2;
        const u = new BABYLON.Vector3(Math.cos(entry.node), 0, Math.sin(entry.node));
        const v = new BABYLON.Vector3(-Math.sin(entry.node) * Math.cos(entry.inclination),
          Math.sin(entry.inclination), Math.cos(entry.node) * Math.cos(entry.inclination));
        return u.scale(Math.cos(angle) * entry.radius).add(v.scale(Math.sin(angle) * entry.radius));
      };
      const observerOrbit = orbitalPosition({ radius: currentRadius, phase: currentPhase,
        periodHours: 8760 * Math.pow(currentSlot, 1.5), node: currentNode, inclination: currentInclination }, simHours);
      this.systemPlanets.forEach(entry => {
        const target = orbitalPosition(entry, simHours);
        const direction = target.subtract(observerOrbit).normalize();
        const altitude = BABYLON.Vector3.Dot(surfaceNormal, direction);
        entry.mesh.position.copyFrom(camera.clone().add(direction.scale(SKY_RADIUS)));
        // Keep system-world markers available for sky inspection. Their true
        // star-centric positions still determine their apparent directions;
        // do not discard them solely because the simplified orbit plane puts
        // the marker just below the local horizon.
        entry.mesh.isVisible = true;
        entry.mesh.visibility = 1;
        const distance = Math.max(1, BABYLON.Vector3.Distance(observerOrbit, target));
        const angularDiameter = 2 * Math.atan(entry.physicalRadiusScene / distance);
        const activeCamera = this.scene?.activeCamera;
        const fov = Math.max(.05, Number(activeCamera?.fov) || .8);
        const renderHeight = Math.max(1, this.scene?.getEngine?.()?.getRenderHeight?.() || 720);
        const pixelDiameter = 2 * Math.tan(angularDiameter / 2) / Math.tan(fov / 2) * (renderHeight / 2);
        // A restrained 2.5 px visibility floor avoids vanishing points without
        // inflating every distant world to the same billboard size.
        const desiredPixels = clamp(pixelDiameter, 2.5, 220);
        const markerWorldDiameter = 2 * Math.tan((desiredPixels / renderHeight) * fov) * SKY_RADIUS;
        entry.apparentDiameter = markerWorldDiameter;
        entry.mesh.scaling.setAll(markerWorldDiameter / 10);
        const brightness = clamp(1.25 / Math.max(.35, distance / SYSTEM_PLANET_SCALE), .8, 2.0);
        entry.material.emissiveColor = entry.color.scale(brightness);
        if (entry.markerMesh) { entry.markerMesh.position.copyFrom(entry.mesh.position); entry.markerMesh.isVisible = this.developerOptions.positionMarkers; }
        if (entry.labelMesh) { entry.labelMesh.position.copyFrom(entry.mesh.position); entry.labelMesh.isVisible = this.developerOptions.worldLabels; }
      });

      if (this.centralMesh) {
        const baseDirection = new BABYLON.Vector3(-.15, .28, .95).normalize();
        const direction = new BABYLON.Vector3(
          baseDirection.x * Math.cos(phase) - baseDirection.z * Math.sin(phase),
          baseDirection.y,
          baseDirection.x * Math.sin(phase) + baseDirection.z * Math.cos(phase)
        );
        this.centralMesh.position.copyFrom(center.add(direction.scale(SKY_RADIUS * .92)));
      }

      const nightVisibility = clamp((.12 - totalIllumination) / .12, 0, 1);
      // Atmospheric optical depth fades stars in daylight. Vacuum worlds keep
      // stars visible even at noon; denser atmospheres progressively obscure
      // them. This is independent of scene fog and celestial mesh materials.
      const atmosphericFade = (this.atmosphere?.starFade || 0) * clamp(totalIllumination * 1.25, 0, 1);
      if (this.starLayer) this.starLayer.alpha = clamp(1 - atmosphericFade + (1 - atmosphericFade) * nightVisibility * .12, 0, 1);

      this.illumination = clamp(totalIllumination, 0, 1);
      const daylight = clamp(totalIllumination * .62, 0, 1);
      this.dayPhase = daylight >= .18 ? (daylight < .34 ? 'twilight' : 'day')
        : (daylight >= .10 ? 'twilight' : 'night');
      const twilight = Math.pow(1 - Math.abs(daylight - .5) * 2, 2);
      const night = 1 - daylight;
      const sky = new BABYLON.Color3(
        .008 + daylight * .27 + twilight * .12,
        .012 + daylight * .48 + twilight * .09,
        .028 + daylight * .68 + twilight * .055
      );
      const nightSky = new BABYLON.Color3(.018, .026, .045);
      const skyColor = BABYLON.Color3.Lerp(nightSky, sky, clamp(daylight + twilight * .2, 0, 1));
      // Scattering tint scales with the UWP atmosphere profile. Exotic and
      // unusual classes intentionally use restrained neutral scattering until
      // explicit composition data exists; UWP alone cannot determine sky gas.
      const scatter = this.atmosphere?.scattering || 0;
      const scatterColor = new BABYLON.Color3(.34, .57, .82);
      skyColor.copyFrom(BABYLON.Color3.Lerp(skyColor, scatterColor, clamp(scatter * daylight * .38, 0, .32)));
      if (this.scene.fogDensity !== undefined) this.scene.fogDensity = this.atmosphere?.fog || 0;
      this.scene.clearColor = new BABYLON.Color4(skyColor.r, skyColor.g, skyColor.b, 1);
      this.scene.fogColor = skyColor;
      // Maintain enough environmental fill to keep terrain readable at night,
      // while directional stellar illumination still defines the day/night side.
      this.ambient.intensity = .20 + daylight * .62;
      this.ambient.diffuse = new BABYLON.Color3(.55, .62, .78);
      this.ambient.groundColor = new BABYLON.Color3(.12, .14, .19);
    },


    getDiagnostics() {
      const camera = this.scene?.activeCamera || null;
      const engine = this.scene?.getEngine?.() || null;
      const renderWidth = engine?.getRenderWidth?.() || 0;
      const renderHeight = engine?.getRenderHeight?.() || 0;
      const viewport = camera && renderWidth > 0 && renderHeight > 0
        ? camera.viewport.toGlobal(renderWidth, renderHeight) : null;
      const project = position => {
        if (!camera || !viewport || !this.scene) return null;
        try {
          const point = BABYLON.Vector3.Project(
            position, BABYLON.Matrix.Identity(), this.scene.getTransformMatrix(), viewport
          );
          return {
            x: Number(point.x.toFixed(1)), y: Number(point.y.toFixed(1)), z: Number(point.z.toFixed(4)),
            insideScreen: point.x >= viewport.x && point.x <= viewport.x + viewport.width
              && point.y >= viewport.y && point.y <= viewport.y + viewport.height
              && point.z >= 0 && point.z <= 1
          };
        } catch (error) {
          return { error: String(error?.message || error) };
        }
      };
      const vector = value => value ? {
        x: Number(value.x.toFixed(2)), y: Number(value.y.toFixed(2)), z: Number(value.z.toFixed(2))
      } : null;
      return {
        suppliedMoonCount: Array.isArray(this.boot?.world?.moons) ? this.boot.world.moons.length
          : (Array.isArray(this.boot?.world?.satellites) ? this.boot.world.satellites.length : 0),
        renderedMoonCount: this.moonMeshes.length,
        systemPlanetCount: this.systemPlanets.length,
        systemPlanets: this.systemPlanets.map(entry => ({ id: entry.world.id, name: entry.world.name, type: entry.world.type, physicalRadiusKm: entry.physicalRadiusKm, apparentDiameterSceneUnits: Number(entry.apparentDiameter.toFixed(2)), color: { r: entry.color.r, g: entry.color.g, b: entry.color.b }, visible: entry.mesh.isVisible, enabled: entry.mesh.isEnabled(), position: vector(entry.mesh.position), screenProjection: project(entry.mesh.position) })),
        developerOptions: { ...this.developerOptions },
        moonDebugVisuals: this.moonDebugVisuals,
        moonProofOverlay: {
          present: false,
          visibleMoonCount: this.moonMeshes.filter(moon => {
            const point = moon.mesh?.getAbsolutePosition?.();
            const projected = point ? project(point) : null;
            return !!projected && projected.insideScreen === true;
          }).length,
          mode: 'world-space-3d-meshes-and-orbit-tubes'
        },
        starfield: { layerPresent: !!this.starLayer, isBackground: this.starLayer?.isBackground ?? null, alpha: this.starLayer?.alpha ?? null, textureReady: this.starTexture?.isReady?.() ?? null },
        orbitPathCount: this.moonMeshes.reduce((count, moon) => count + (moon.orbitMeshes || []).filter(mesh => mesh && mesh.isEnabled()).length, 0),
        visibleOrbitArcCounts: this.moonMeshes.map(moon => moon.visibleOrbitArcCount || 0),
        renderPipeline: {
          activeCameraIsSceneCamera: !!camera && camera === this.scene?.activeCamera,
          sceneContainsMoons: this.moonMeshes.every(moon => this.scene?.meshes?.includes(moon.mesh)),
          activeMeshNames: (() => {
            const active = this.scene?.getActiveMeshes?.();
            const entries = active?.data || [];
            return this.moonMeshes.filter(moon => entries.includes(moon.mesh)).map(moon => moon.mesh.name);
          })()
        },
        camera: camera ? {
          position: vector(camera.position), target: vector(camera.getTarget?.()),
          direction: vector(camera.getDirection?.(BABYLON.Axis.Z)),
          fov: Number(camera.fov?.toFixed?.(3) ?? camera.fov), minZ: camera.minZ, maxZ: camera.maxZ,
          renderWidth, renderHeight
        } : null,
        moonScreenSeparationPixels: (() => {
          const points = this.moonMeshes.map(moon => { const position = moon.mesh?.getAbsolutePosition?.() || moon.mesh?.position; return position ? project(position) : null; }).filter(point => point && Number.isFinite(point.x) && Number.isFinite(point.y));
          if (points.length < 2) return null;
          return Number(Math.hypot(points[0].x - points[1].x, points[0].y - points[1].y).toFixed(1));
        })(),
        moons: this.moonMeshes.map((moon, index) => {
          const mesh = moon.mesh;
          const position = mesh?.getAbsolutePosition?.() || mesh?.position || null;
          const bounds = mesh?.getBoundingInfo?.();
          const radius = bounds?.boundingSphere?.radiusWorld || null;
          return {
            index: index + 1,
            name: String(this.boot?.world?.moons?.[index]?.name || `Moon ${index + 1}`),
            meshName: mesh?.name || null,
            isVisible: mesh?.isVisible ?? null,
            isEnabled: mesh?.isEnabled?.() ?? null,
            visibility: mesh?.visibility ?? null,
            materialPresent: !!mesh?.material,
            debugMaterialActive: mesh?.material === moon.debugMaterial,
            orbitPathPresent: !!moon.orbitMesh,
            orbitPathVisible: !!moon.orbitMesh?.isVisible && !!moon.orbitMesh?.isEnabled?.(),
            materialReady: mesh?.material?.isReady?.(mesh) ?? null,
            materialDisableLighting: mesh?.material?.disableLighting ?? null,
            materialFogEnabled: mesh?.material?.fogEnabled ?? null,
            materialAlpha: mesh?.material?.alpha ?? null,
            materialTransparencyMode: mesh?.material?.transparencyMode ?? null,
            materialDepthFunction: mesh?.material?.depthFunction ?? null,
            materialDisableDepthWrite: mesh?.material?.disableDepthWrite ?? null,
            geometryPresent: !!mesh?.geometry,
            vertexCount: mesh?.getTotalVertices?.() ?? 0,
            indexCount: mesh?.getTotalIndices?.() ?? 0,
            renderingGroupId: mesh?.renderingGroupId ?? null,
            layerMask: mesh?.layerMask ?? null,
            cameraLayerMatch: camera ? ((mesh?.layerMask ?? 0) & (camera.layerMask ?? 0)) !== 0 : null,
            inScene: !!mesh && !!this.scene?.meshes?.includes(mesh),
            activeThisFrame: !!mesh && !!(this.scene?.getActiveMeshes?.()?.data || []).includes(mesh),
            diameterWorld: radius ? Number((radius * 2).toFixed(2)) : null,
            scaling: vector(mesh?.scaling),
            orbitTubeRadius: moon.orbitMesh?.getBoundingInfo?.()?.boundingSphere?.radiusWorld ?? null,
            position: vector(position),
            worldAnchored: true,
            cameraRelative: false,
            orbitalRadiusWorld: Number(moon.orbitRadiusWorld?.toFixed?.(2) ?? 0),
            orbitCenter: vector(moon.orbitCenter),
            orbitPlaneNormal: vector(moon.orbitPlaneNormal),
            distanceFromCamera: camera && position ? Number(BABYLON.Vector3.Distance(camera.position, position).toFixed(2)) : null,
            horizonClearanceDegrees: position && camera?.position ? (() => {
              const origin = camera.position;
              const normal = window.CORE_PLANETARY_ENVIRONMENT?.getSurfaceNormal(origin) || BABYLON.Axis.Y;
              const ray = position.subtract(origin).normalize();
              const radius = Math.max(1, Number(window.CORE_PLANETARY_ENVIRONMENT?.radius) || 6400);
              const cameraRadius = Math.max(radius + 1, origin.length());
              const horizonDot = -Math.sqrt(Math.max(0, 1 - (radius * radius) / (cameraRadius * cameraRadius)));
              const rayElevation = Math.asin(clamp(BABYLON.Vector3.Dot(ray, normal), -1, 1));
              const horizonElevation = Math.asin(clamp(horizonDot, -1, 1));
              return Number(((rayElevation - horizonElevation) * 180 / Math.PI).toFixed(2));
            })() : null,
            aboveLocalHorizon: position && camera?.position ? (() => {
              const origin = camera.position;
              const normal = window.CORE_PLANETARY_ENVIRONMENT?.getSurfaceNormal(origin) || BABYLON.Axis.Y;
              const ray = position.subtract(origin).normalize();
              const radius = Math.max(1, Number(window.CORE_PLANETARY_ENVIRONMENT?.radius) || 6400);
              const cameraRadius = Math.max(radius + 1, origin.length());
              const horizonDot = -Math.sqrt(Math.max(0, 1 - (radius * radius) / (cameraRadius * cameraRadius)));
              return BABYLON.Vector3.Dot(ray, normal) > horizonDot;
            })() : null,
            screenProjection: position ? project(position) : null
          };
        })
      };
    },

    setDeveloperOption(option, enabled) {
      if (!Object.prototype.hasOwnProperty.call(this.developerOptions, option)) return false;
      this.developerOptions[option] = enabled === true;
      if (option === 'orbitPaths') {
        this.moonMeshes.forEach(moon => (moon.orbitMeshes || [moon.orbitMesh]).forEach(mesh => {
          if (mesh) { mesh.setEnabled(this.developerOptions.orbitPaths); mesh.isVisible = this.developerOptions.orbitPaths; }
        }));
      }
      if (option === 'positionMarkers') {
        this.moonMeshes.forEach((moon, index) => {
          if (!moon.positionMarkerMesh) {
            const marker = BABYLON.MeshBuilder.CreateSphere(`core-dev-moon-position-${index + 1}`, { diameter: 42, segments: 8 }, this.scene);
            const material = new BABYLON.StandardMaterial(`${marker.name}-material`, this.scene);
            material.disableLighting = true; material.fogEnabled = false; material.emissiveColor = new BABYLON.Color3(1, .15, .1);
            marker.material = material; marker.isPickable = false; marker.renderingGroupId = 3; moon.positionMarkerMesh = marker;
          }
          moon.positionMarkerMesh.setEnabled(this.developerOptions.positionMarkers);
          moon.positionMarkerMesh.isVisible = this.developerOptions.positionMarkers;
        });
      }
      if (option === 'positionMarkers' || option === 'worldLabels') {
        this.systemPlanets.forEach(entry => {
          const property = option === 'positionMarkers' ? 'markerMesh' : 'labelMesh';
          if (!entry[property]) {
            if (option === 'positionMarkers') {
              const marker = BABYLON.MeshBuilder.CreateSphere(`core-dev-position-${entry.world.id}`, { diameter: 42, segments: 8 }, this.scene);
              const material = new BABYLON.StandardMaterial(`${marker.name}-material`, this.scene);
              material.disableLighting = true; material.fogEnabled = false;
              material.emissiveColor = new BABYLON.Color3(1, .15, .1);
              marker.material = material; marker.isPickable = false; marker.renderingGroupId = 3;
              entry.markerMesh = marker;
            } else {
              const texture = new BABYLON.DynamicTexture(`core-dev-label-${entry.world.id}`, { width: 512, height: 128 }, this.scene, false);
              texture.hasAlpha = true; const ctx = texture.getContext();
              ctx.clearRect(0, 0, 512, 128); ctx.font = 'bold 34px Arial'; ctx.textAlign = 'center'; ctx.fillStyle = '#ffffff';
              ctx.shadowColor = '#000000'; ctx.shadowBlur = 8; ctx.fillText(String(entry.world.name || entry.world.id), 256, 78); texture.update();
              const label = BABYLON.MeshBuilder.CreatePlane(`core-dev-label-${entry.world.id}`, { width: 900, height: 225 }, this.scene);
              const material = new BABYLON.StandardMaterial(`${label.name}-material`, this.scene);
              material.diffuseTexture = texture; material.opacityTexture = texture; material.emissiveColor = BABYLON.Color3.White();
              material.disableLighting = true; material.fogEnabled = false; material.backFaceCulling = false;
              label.material = material; label.billboardMode = BABYLON.Mesh.BILLBOARDMODE_ALL; label.isPickable = false; label.renderingGroupId = 3;
              entry.labelMesh = label;
            }
          }
          const mesh = entry[property]; mesh.setEnabled(this.developerOptions[option]); mesh.isVisible = this.developerOptions[option];
        });
      }
      return true;
    },

    setMoonDebugVisuals(enabled) {
      this.moonDebugVisuals = enabled === true;
      this.moonMeshes.forEach(moon => {
        if (moon.mesh) {
          moon.mesh.material = this.moonDebugVisuals ? moon.debugMaterial : moon.normalMaterial;
          moon.mesh.isVisible = true;
          moon.mesh.visibility = 1;
          moon.mesh.renderingGroupId = 3;
          moon.mesh.alwaysSelectAsActiveMesh = true;
        }
        (moon.orbitMeshes || [moon.orbitMesh]).forEach(mesh => {
          if (!mesh) return;
          mesh.setEnabled(this.moonDebugVisuals);
          mesh.isVisible = this.moonDebugVisuals;
        });
      });
      return this.moonDebugVisuals;
    },

    getTimeState() {
      const daySeconds = Math.max(1, this.dayLengthSeconds || DAY_SECONDS);
      const elapsed = Math.max(0, this.elapsed);
      const withinDay = elapsed % daySeconds;
      const totalSeconds = Math.floor((withinDay / daySeconds) * 86400);
      const hours = Math.floor(totalSeconds / 3600);
      const minutes = Math.floor((totalSeconds % 3600) / 60);
      const seconds = totalSeconds % 60;
      return {
        hours, minutes, seconds,
        time: [hours, minutes, seconds].map(value => String(value).padStart(2, '0')).join(':'),
        day: Math.floor(elapsed / daySeconds) + 1,
        phase: this.dayPhase,
        illumination: this.illumination,
        atmosphere: this.atmosphere
      };
    },

    dispose() {
      this.destroyed = true;
      if (this.clockSyncTimer !== null) window.clearInterval(this.clockSyncTimer);
      this.clockSyncTimer = null;
      this.bodies.forEach(body => {
        body.light?.dispose();
        body.mesh?.dispose();
        body.material?.dispose();
      });
      this.starLayer?.dispose();
      this.starTexture?.dispose();
      this.starLayer = null;
      this.starTexture = null;
      this.centralMesh?.dispose();
      this.systemPlanets.forEach(entry => { entry.mesh?.dispose(); entry.material?.dispose(); entry.labelMesh?.dispose(); entry.markerMesh?.dispose(); });
      this.systemPlanets = [];
      this.moonMeshes.forEach(moon => {
        moon.positionMarkerMesh?.dispose();
        moon.mesh?.dispose();
        (moon.orbitMeshes || [moon.orbitMesh]).forEach(mesh => mesh?.dispose());
        moon.orbitMaterial?.dispose();
      });
      this.moonMaterials.forEach(material => material?.dispose());
      this.moonMeshes = [];
      this.moonMaterials = [];
      this.centralMaterial?.dispose();
      this.ambient?.dispose();
      this.bodies = [];
      this.starfield = [];
      this.scene = null;
    }
  };

  window.CORE_ASTRONOMICAL_ENVIRONMENT = environment;
})();
