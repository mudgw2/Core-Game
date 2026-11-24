(() => {
  'use strict';

  const canvas = document.getElementById('worldCanvas');
  const loading = document.getElementById('worldLoading');
  const errorNode = document.getElementById('worldError');
  const coordinatesNode = document.getElementById('worldCoordinates');
  const localTimeNode = document.getElementById('worldLocalTime');
  const dayIconNode = document.getElementById('worldDayIcon');
  const dayCountNode = document.getElementById('worldDayCount');
  const motionNode = document.getElementById('worldMotionStatus');
  const overlay = document.getElementById('worldOverlay');
  const overlayTitle = document.getElementById('worldOverlayTitle');
  const overlayContent = document.getElementById('worldOverlayContent');
  const mapOverlay = document.getElementById('worldMapOverlay');
  const mapCanvas = document.getElementById('worldMapCanvas');
  const mapLocation = document.getElementById('worldMapLocation');
  const boot = window.CORE_WORLD_BOOT || {};
  if (!canvas) return;

  const showError = (message) => {
    if (loading) loading.classList.add('hidden');
    if (errorNode) {
      errorNode.textContent = message;
      errorNode.classList.remove('hidden');
    }
  };

  if (!window.BABYLON) {
    showError('Babylon.js could not load. Check the browser network connection and refresh this page.');
    return;
  }
  if (!BABYLON.SceneLoader) {
    showError('The Babylon.js GLB loader could not load. Refresh the page and try again.');
    return;
  }
  if (!boot.character || !boot.world || !boot.system) {
    showError('The selected character or world data is incomplete. Return to System Overview and select a world again.');
    return;
  }

  let engine;
  let scene;
  let playerRoot;
  let playerVisualRoot = null;
  let groundPosition = null;
  let jumpHeight = 0;
  let jumpVelocity = 0;
  const jumpImpulse = 7.2;
  const gravityAcceleration = 18;
  let walkingAnimation = null;
  let runningAnimation = null;
  let idleAnimation = null;
  let activeAnimation = null;
  let jumpStartAnimation = null;
  let jumpAirAnimation = null;
  let jumpLandAnimation = null;
  let jumpAnimationPhase = 'grounded';
  let disposed = false;
  let lastFrame = performance.now();
  let multiplayerClient = null;
  let multiplayerRoom = null;
  let lastInputSent = 0;
  let inputSequence = 0;
  let authoritativeTransform = null;
  const remotePlayers = new Map();
  const multiplayerUrl = String(boot.multiplayerUrl || '').trim();
  const multiplayerStatus = document.getElementById('worldMultiplayerStatus');
  const pressed = new Set();
  const movementKeys = new Set(['w', 'a', 's', 'd', 'arrowup', 'arrowdown', 'arrowleft', 'arrowright', 'shift', ' ']);
  const characterAppearance = window.CORE_APPEARANCE
    ? window.CORE_APPEARANCE.normalize(boot.character.appearance)
    : { tint: /^#[0-9a-f]{6}$/i.test(String(boot.character.appearance?.tint || '')) ? String(boot.character.appearance.tint) : '#61d8e8' };
  const speedWalk = 3.2;
  const speedRun = 6.0;
  let currentHex = null;
  let mapSvg = null;
  let mapPlayerMarker = null;
  let activePanel = null;
  let developerFlyMode = false;
  let flySpeed = 45;

  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
  function alignUpToSurface(normal) {
    const target = normal.clone().normalize();
    const up = BABYLON.Axis.Y;
    const dot = Math.max(-1, Math.min(1, BABYLON.Vector3.Dot(up, target)));
    if (dot > 0.999999) return BABYLON.Quaternion.Identity();
    if (dot < -0.999999) return BABYLON.Quaternion.RotationAxis(BABYLON.Axis.X, Math.PI);
    const axis = BABYLON.Vector3.Cross(up, target).normalize();
    return BABYLON.Quaternion.RotationAxis(axis, Math.acos(dot));
  }
  function orientCharacterToSurface(normal, heading) {
    const up = normal.clone().normalize();
    const forward = heading.clone().subtract(up.scale(BABYLON.Vector3.Dot(heading, up)));
    if (forward.lengthSquared() < 0.000001) {
      forward.copyFrom(BABYLON.Axis.Z).subtractInPlace(up.scale(BABYLON.Vector3.Dot(BABYLON.Axis.Z, up)));
    }
    forward.normalize();
    const right = BABYLON.Vector3.Cross(up, forward).normalize();
    const correctedForward = BABYLON.Vector3.Cross(right, up).normalize();
    // Build the rotation matrix explicitly. Babylon.js does not expose
    // Matrix.FromXYZAxes in the CDN build used by CORE. The basis vectors
    // are the matrix columns (local X=right, Y=up, Z=forward).
    // Babylon's Matrix.FromValues stores matrix columns consecutively.
    // Each local axis must therefore be supplied as one column: right, up,
    // then forward. The previous row-oriented layout transposed the basis,
    // rotating the skinned humanoid into the terrain so only extremities showed.
    const basis = BABYLON.Matrix.FromValues(
      right.x, right.y, right.z, 0,
      up.x, up.y, up.z, 0,
      correctedForward.x, correctedForward.y, correctedForward.z, 0,
      0, 0, 0, 1
    );
    return BABYLON.Quaternion.FromRotationMatrix(basis);
  }
  function rotateVectorBetweenNormals(vector, fromNormal, toNormal) {
    const from = fromNormal.clone().normalize();
    const to = toNormal.clone().normalize();
    const dot = Math.max(-1, Math.min(1, BABYLON.Vector3.Dot(from, to)));
    if (dot > 0.999999) return vector.clone();
    let axis = BABYLON.Vector3.Cross(from, to);
    if (axis.lengthSquared() < 0.000001) {
      axis = BABYLON.Vector3.Cross(from, Math.abs(from.x) < 0.8 ? BABYLON.Axis.X : BABYLON.Axis.Z);
    }
    axis.normalize();
    const rotation = BABYLON.Matrix.RotationAxis(axis, Math.acos(dot));
    return BABYLON.Vector3.TransformNormal(vector, rotation);
  }

  function positionCameraAboveSurface(camera, target, normal, previousNormal = null) {
    const surfaceUp = normal.clone().normalize();
    let offset;
    if (previousNormal) {
      const previousTarget = camera.getTarget().clone();
      const currentOffset = camera.position.subtract(previousTarget);
      offset = rotateVectorBetweenNormals(currentOffset, previousNormal, surfaceUp);
    } else {
      let backward = new BABYLON.Vector3(0, 0, -1);
      backward.subtractInPlace(surfaceUp.scale(BABYLON.Vector3.Dot(backward, surfaceUp)));
      if (backward.lengthSquared() < 0.0001) {
        backward = BABYLON.Vector3.Cross(surfaceUp, BABYLON.Axis.X);
      }
      backward.normalize();
      offset = surfaceUp.scale(3.2).add(backward.scale(6.8));
    }
    // Keep the camera outside the terrain even on steep local slopes.
    if (camera._coreFirstPersonMode === true) {
      // In first-person the camera target is the eye point. Do not push the
      // camera back out to the third-person terrain-clearance radius.
      camera.upVector = surfaceUp.clone();
      camera.setTarget(target);
      return;
    }
    const radialOffset = BABYLON.Vector3.Dot(offset, surfaceUp);
    if (radialOffset < 2.5) offset.addInPlace(surfaceUp.scale(2.5 - radialOffset));
    // ArcRotateCamera requires assignment so its internal up-to-Y matrices are rebuilt.
    // Mutating upVector with copyFrom bypasses setMatUp(), leaving _upToYMatrix undefined.
    camera.upVector = surfaceUp.clone();
    camera.setTarget(target);
    const cameraPosition = target.add(offset);
    const cameraNormal = window.CORE_PLANETARY_ENVIRONMENT.getSurfaceNormal(cameraPosition);
    const cameraCoordinates = window.CORE_PLANETARY_ENVIRONMENT.getCoordinates(cameraPosition);
    const cameraTerrain = window.CORE_PLANETARY_ENVIRONMENT.sampleAtCoordinates(cameraCoordinates.latitude, cameraCoordinates.longitude);
    const minimumCameraRadius = window.CORE_PLANETARY_ENVIRONMENT.radius + cameraTerrain.elevation + 2.0;
    if (BABYLON.Vector3.Dot(cameraPosition, cameraNormal) < minimumCameraRadius) {
      cameraPosition.copyFrom(cameraNormal.scale(minimumCameraRadius));
    }
    camera.setPosition(cameraPosition);
  }

  function keepCameraAboveTerrain(camera) {
    if (!camera || camera._coreFirstPersonMode === true || !window.CORE_PLANETARY_ENVIRONMENT) return;
    const position = camera.position;
    const normal = window.CORE_PLANETARY_ENVIRONMENT.getSurfaceNormal(position);
    const coordinates = window.CORE_PLANETARY_ENVIRONMENT.getCoordinates(position);
    const terrain = window.CORE_PLANETARY_ENVIRONMENT.sampleAtCoordinates(coordinates.latitude, coordinates.longitude);
    const minimumRadius = window.CORE_PLANETARY_ENVIRONMENT.radius + terrain.elevation + 2.0;
    const radialDistance = BABYLON.Vector3.Dot(position, normal);
    if (radialDistance < minimumRadius) {
      camera.setPosition(normal.scale(minimumRadius));
    }
  }

  function closePanels() {
    overlay?.classList.add('hidden');
    mapOverlay?.classList.add('hidden');
    activePanel = null;
  }
  function showPanel(panelKey, title, content) {
    if (!overlay || !overlayTitle || !overlayContent) return;
    if (activePanel === panelKey && !overlay.classList.contains('hidden')) {
      closePanels();
      return;
    }
    mapOverlay?.classList.add('hidden');
    activePanel = panelKey;
    overlayTitle.textContent = title;
    overlayContent.innerHTML = content;
    overlay.classList.remove('hidden');
  }
  function showMainMenu() {
    const exitUrl = `${String(boot.apiRoot || '').replace(/\/$/, '')}/player/game.php?character_id=${encodeURIComponent(boot.character.id)}&system_id=${encodeURIComponent(boot.system.id)}`;
    showPanel('menu', 'Game Menu', `
      <div class="world-menu-grid">
        <button type="button" class="world-menu-primary" data-resume>Resume</button>
        <button type="button" data-menu="settings">Settings</button>
        <button type="button" data-menu="keybindings">Keybindings</button>
        <button type="button" data-menu="movement">Movement</button>
        ${boot.developerMode === true ? '<button type="button" data-menu="developer-options">Developer Options</button><button type="button" data-menu="world-information">World Information</button>' : ''}
        <a class="world-menu-exit" href="${escapeHtml(exitUrl)}">Exit Test World</a>
      </div>`);
  }
  function showEnvironmentalBriefing() {
    const world = boot.world || {};
    const physical = world.physical || {};
    const rows = [
      ['World', world.name || 'Unnamed world'],
      ['World type', world.world_type || world.type || world.classification || 'Not specified'],
      ['Gravity', physical.gravity_g ?? world.gravity ?? world.surface_gravity ?? 'Unknown'],
      ['Radius', physical.radius_km ?? world.radius_km ?? world.radius ?? 'Unknown'],
      ['Rotation period', physical.rotation_period_hours ?? world.rotation_period_hours ?? world.day_length_hours ?? 'Unknown'],
      ['Axial tilt', physical.axial_tilt_degrees ?? world.axial_tilt_degrees ?? 'Unknown'],
      ['Atmosphere', world.atmosphere_description || world.atmosphere || world.atmosphere_code || 'Not yet characterized'],
      ['Hydrographics', world.hydrographics_percent ?? world.hydrographics ?? 'Unknown'],
      ['Climate', world.climate?.summary || world.climate_description || 'Not yet characterized'],
      ['Known geography', world.geography?.summary || 'Not yet surveyed']
    ];
    const html = `<p class="world-panel-note">Current world profile. Unknown or uncharacterized values have not yet been established by the generated world data.</p><dl class="world-environment-briefing">${rows.map(([label, value]) => `<div><dt>${escapeHtml(label)}</dt><dd>${escapeHtml(value)}</dd></div>`).join('')}</dl>`;
    showPanel('environment-briefing', 'World Environment', html);
  }

  function showWorldInformation() {
    if (boot.developerMode !== true) return;
    const world = boot.world || {};
    const environment = window.CORE_PLANETARY_ENVIRONMENT || {};
    const astronomy = window.CORE_ASTRONOMICAL_ENVIRONMENT || {};
    const radius = Number(environment.radius || 0);
    const dimensionRows = [
      ['Radius (km)', radius || world.radius_km || world.radius || null],
      ['Diameter (km)', radius ? radius * 2 : (world.diameter_km || world.diameter || null)],
      ['Surface area (km²)', radius ? (4 * Math.PI * radius * radius).toFixed(0) : null],
      ['Gravity', world.gravity ?? world.surface_gravity ?? null],
      ['Atmosphere', world.atmosphere ?? world.atmosphere_code ?? null],
      ['Hydrographics', world.hydrographics ?? world.hydrographics_percent ?? null],
      ['Technology Level', world.technology_level ?? world.tech_level ?? null],
      ['Rotation period (hours)', world.rotation_period_hours ?? world.day_length_hours ?? null]
    ];
    const valueText = value => value === null || value === undefined || value === '' ? 'Not defined in supplied data' : String(value);
    const geography = environment.geography ?? world.geography ?? null;
    const sections = [
      ['World identity', { id: world.id, name: world.name, seed: world.seed, uwp: world.uwp, generation_fingerprint: world.generation_fingerprint }],
      ['Planetary dimensions & properties', Object.fromEntries(dimensionRows.map(([label, value]) => [label, valueText(value)]))],
      ['Geography data', geography],
      ['Full world record (backend payload)', world],
      ['System context', boot.system || {}],
      ['Runtime environment', {
        renderer_radius: environment.radius ?? null,
        geography_available: !!environment.geography,
        astronomy_phase: astronomy.dayPhase ?? null,
        local_time: astronomy.getTimeState?.() ?? null
      }],
      ['Moon renderer diagnostics', astronomy.getDiagnostics?.() ?? {
        error: 'Moon diagnostic API is not available in the loaded astronomy script.'
      }]
    ];
    const html = sections.map(([title, data], index) => `<details class="world-debug-section" ${index < 3 ? 'open' : ''}><summary>${escapeHtml(title)}</summary><pre>${escapeHtml(JSON.stringify(data, null, 2))}</pre></details>`).join('');
    showPanel('world-information', 'World Information · Developer', `<p class="world-panel-note">Read-only inspection of the world payload and current runtime presentation. Missing values are shown as undefined; no data is generated by this panel.</p>${html}`);
  }
  function showDeveloperOptions() {
    if (boot.developerMode !== true) return;
    const astronomy = window.CORE_ASTRONOMICAL_ENVIRONMENT;
    const saved = (() => { try { return JSON.parse(localStorage.getItem('core.celestialDeveloperOptions') || '{}'); } catch (_) { return {}; } })();
    const options = [
      ['orbitPaths', 'Show lunar orbital paths'],
      ['positionMarkers', 'Show celestial position markers'],
      ['worldLabels', 'Show system-world labels'],
      ['sizeDiagnostics', 'Show physical/apparent size diagnostics']
    ];
    const rows = options.map(([key, label]) => `<label class="world-dev-option"><input type="checkbox" data-celestial-option="${key}" ${saved[key] === true ? 'checked' : ''}><span>${label}</span></label>`).join('');
    showPanel('developer-options', 'Developer Options', `<p class="world-panel-note">Read-only visual diagnostics. These controls do not modify generated worlds, physical data, orbital simulation, or saved universe state. All options default OFF.</p><div class="world-dev-options">${rows}</div><pre id="celestialSizeDiagnostics" class="world-dev-readout"></pre><button type="button" class="world-menu-back" data-menu-back>Back to Menu</button>`);
    options.forEach(([key]) => astronomy?.setDeveloperOption?.(key, saved[key] === true));
    const updateReadout = () => {
      const node = document.getElementById('celestialSizeDiagnostics');
      if (!node) return;
      const data = astronomy?.getDiagnostics?.();
      node.textContent = astronomy?.developerOptions?.sizeDiagnostics
        ? JSON.stringify({ options: data?.developerOptions, worlds: (data?.systemPlanets || []).map(world => ({ name: world.name, type: world.type, physicalRadiusKm: world.physicalRadiusKm, apparentDiameterSceneUnits: world.apparentDiameterSceneUnits })) }, null, 2)
        : 'Size diagnostics are off.';
    };
    overlayContent.querySelectorAll('[data-celestial-option]').forEach(input => input.addEventListener('change', () => {
      const key = input.dataset.celestialOption;
      const current = (() => { try { return JSON.parse(localStorage.getItem('core.celestialDeveloperOptions') || '{}'); } catch (_) { return {}; } })();
      current[key] = input.checked;
      try { localStorage.setItem('core.celestialDeveloperOptions', JSON.stringify(current)); } catch (_) {}
      astronomy?.setDeveloperOption?.(key, input.checked);
      updateReadout();
    }));
    updateReadout();
  }

  function showMenuSection(section) {
    const sections = {
      settings: ['Settings', '<p>Gameplay settings will be added here as the environment develops.</p>'],
      keybindings: ['Keybindings', '<dl class="world-key-list"><div><dt>W / S or Up / Down</dt><dd>Move forward / backward</dd></div><div><dt>A / D or Left / Right</dt><dd>Turn left / right</dd></div><div><dt>Mouse drag</dt><dd>Orbit and look up/down</dd></div><div><dt>Mouse wheel</dt><dd>Zoom camera</dd></div><div><dt>F</dt><dd>Toggle close / third-person camera</dd></div><div><dt>F6</dt><dd>Toggle developer fly mode; WASD to move, Page Up / Page Down to rise or descend</dd></div><div><dt>Esc</dt><dd>Game menu / close panel</dd></div><div><dt>M</dt><dd>Planetary map</dd></div><div><dt>V</dt><dd>World environment</dd></div><div><dt>C</dt><dd>Character information</dd></div><div><dt>I</dt><dd>Inventory</dd></div><div><dt>Shift</dt><dd>Run</dd></div><div><dt>Spacebar</dt><dd>Jump</dd></div></dl>'],
      movement: ['Movement', '<p><strong>W / S</strong> move forward and backward. <strong>A / D</strong> turn your character left and right; the arrow keys provide the same controls. Hold <strong>Shift</strong> to run. Press <strong>Spacebar</strong> to jump. Drag with the mouse to orbit and look up or down. Use the mouse wheel to zoom into first-person, and press <strong>F</strong> to cycle third-person, close, and first-person views.</p>']
    };
    if (section === 'developer-options') { showDeveloperOptions(); return; }
    if (section === 'world-information') { showWorldInformation(); return; }
    const selected = sections[section];
    if (!selected) return;
    showPanel(`menu-${section}`, selected[0], `${selected[1]}<button type="button" class="world-menu-back" data-menu-back>Back to Menu</button>`);
  }
  function showCharacterInfo() {
    const character = boot.character || {};
    const rows = [['Name', character.name], ['Species', character.species], ['Role', character.role], ['Age', character.age], ['Level', character.level]]
      .filter(([, value]) => value !== undefined && value !== null && value !== '');
    showPanel('character', 'Character Information', `<div class="world-info-grid">${rows.map(([label, value]) => `<div><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`).join('')}</div><p class="world-panel-note">Character details are read from the current character record. More character statistics will appear here as those systems are implemented.</p>`);
  }
  function showInventory() {
    const inventory = Array.isArray(boot.character?.inventory) ? boot.character.inventory : [];
    const rows = inventory.map((item, index) => {
      const label = typeof item === 'string' ? item : (item?.name || item?.label || item?.item_id || `Item ${index + 1}`);
      const detail = typeof item === 'object' && item ? (item.quantity ? ` · Quantity ${item.quantity}` : '') : '';
      return `<li>${escapeHtml(label)}${escapeHtml(detail)}</li>`;
    }).join('');
    showPanel('inventory', 'Inventory', rows ? `<ul class="world-inventory-list">${rows}</ul>` : '<div class="world-empty-state"><strong>Inventory is not configured yet.</strong><p>The inventory interface is ready for the item and equipment systems, but this character does not currently have inventory records.</p></div>');
  }
  function terrainClass(terrain) {
    const value = String(terrain || '').toLowerCase();
    return ['water', 'plains', 'hills', 'mountains'].includes(value) ? value : 'plains';
  }
  function buildWorldMap() {
    const environment = window.CORE_PLANETARY_ENVIRONMENT;
    if (!mapCanvas || !environment?.geography) return;
    const cells = [];
    const columns = 144, rows = 72, cellWidth = 720 / columns, cellHeight = 360 / rows;
    for (let row = 0; row < rows; row++) {
      const latitude = 90 - ((row + .5) / rows) * 180;
      for (let column = 0; column < columns; column++) {
        const longitude = -180 + ((column + .5) / columns) * 360;
        const surface = environment.sampleAtCoordinates(latitude, longitude);
        const color = surface.color.map(channel => Math.round(channel * 255)).join(',');
        cells.push(`<rect x="${(column * cellWidth).toFixed(2)}" y="${(row * cellHeight).toFixed(2)}" width="${(cellWidth + .1).toFixed(2)}" height="${(cellHeight + .1).toFixed(2)}" fill="rgb(${color})"><title>${latitude.toFixed(1)}°, ${longitude.toFixed(1)}° · ${escapeHtml(surface.terrain)}</title></rect>`);
      }
    }
    mapCanvas.innerHTML = `<svg class="planetary-map-svg" viewBox="0 0 720 360" role="img" aria-label="Global planetary map">${cells.join('')}<circle id="worldMapPlayerMarker" class="map-player-marker" cx="360" cy="180" r="5"><title>Character location</title></circle></svg>`;
    mapSvg = mapCanvas.querySelector('svg');
    mapPlayerMarker = mapCanvas.querySelector('#worldMapPlayerMarker');
    updateWorldMapMarker();
  }
  function updateWorldMapMarker() {
    if (!mapPlayerMarker || !playerRoot) return;
    const coordinates = window.CORE_PLANETARY_ENVIRONMENT.getCoordinates(groundPosition);
    // Equirectangular global projection: 0° latitude / 0° longitude is exactly the map center.
    const x = 360 + (coordinates.longitude * 2);
    const y = 180 - (coordinates.latitude * 2);
    mapPlayerMarker.setAttribute('cx', String(x));
    mapPlayerMarker.setAttribute('cy', String(y));
    const altitudeKm = window.CORE_PLANETARY_ENVIRONMENT.getAltitude(groundPosition);
    const altitudeMeters = Math.round(altitudeKm * 1000);
    const altitudeLabel = `${altitudeMeters >= 0 ? '+' : ''}${altitudeMeters.toLocaleString()} m (${altitudeKm >= 0 ? '+' : ''}${altitudeKm.toFixed(3)} km)`;
    if (mapLocation) mapLocation.textContent = `Current location: ${coordinates.latitude.toFixed(3)}° latitude, ${coordinates.longitude.toFixed(3)}° longitude · Altitude: ${altitudeLabel}`;
  }
  function openWorldMap() {
    if (!mapOverlay) return;
    overlay?.classList.add('hidden');
    mapOverlay.classList.remove('hidden');
    activePanel = 'map';
    if (!mapSvg) buildWorldMap();
    updateWorldMapMarker();
  }

  function createProceduralIdleAnimation(skeletons = []) {
    const group = new BABYLON.AnimationGroup('CORE-Standing-Idle', scene);
    const targets = [];
    skeletons.forEach(skeleton => {
      (skeleton.bones || []).forEach(bone => {
        const name = String(bone.name || '').toLowerCase();
        if (!/(spine1|spine2|^mixamorig:spine$|mixamorig:head$)/i.test(name)) return;
        const base = bone.getRotationQuaternion(BABYLON.Space.LOCAL) || BABYLON.Quaternion.Identity();
        const axis = name.includes('head') ? BABYLON.Axis.X : BABYLON.Axis.X;
        const amplitude = name.includes('head') ? .012 : (name.includes('spine2') ? .018 : .009);
        const delta = BABYLON.Quaternion.RotationAxis(axis, amplitude);
        const forward = base.multiply(delta);
        const backward = base.multiply(BABYLON.Quaternion.RotationAxis(axis, -amplitude * .45));
        const animation = new BABYLON.Animation(
          `CORE-idle-${bone.name}`, 'rotationQuaternion', 30,
          BABYLON.Animation.ANIMATIONTYPE_QUATERNION,
          BABYLON.Animation.ANIMATIONLOOPMODE_CYCLE
        );
        animation.setKeys([
          { frame: 0, value: base.clone() },
          { frame: 45, value: forward },
          { frame: 90, value: backward },
          { frame: 120, value: base.clone() }
        ]);
        group.addTargetedAnimation(animation, bone);
        targets.push(bone);
      });
    });
    if (!targets.length && playerRoot) {
      const animation = new BABYLON.Animation(
        'CORE-idle-breathing', 'position.y', 30,
        BABYLON.Animation.ANIMATIONTYPE_FLOAT,
        BABYLON.Animation.ANIMATIONLOOPMODE_CYCLE
      );
      animation.setKeys([
        { frame: 0, value: playerRoot.position.y },
        { frame: 45, value: playerRoot.position.y + .012 },
        { frame: 90, value: playerRoot.position.y - .006 },
        { frame: 120, value: playerRoot.position.y }
      ]);
      group.addTargetedAnimation(animation, playerRoot);
    }
    group.normalize(0, 120);
    return group;
  }

  // Keep AnimationGroup playback on Babylon's established start/stop path.
  // Per-group blending caused imported GLB clips to lose reliable playback;
  // jump physics and clip sequencing remain independent of this presentation.
  function setAnimation(group) {
    if (!group || activeAnimation === group) return;
    if (activeAnimation) activeAnimation.stop();
    activeAnimation = group;
    activeAnimation.start(true, 1, activeAnimation.from, activeAnimation.to, false);
    activeAnimation.speedRatio = activeAnimation === runningAnimation ? 1.35
      : activeAnimation === walkingAnimation ? .85 : 1;
  }

  function playJumpClip(group, onEnd) {
    if (!group) { onEnd?.(); return false; }
    if (activeAnimation && activeAnimation !== group) activeAnimation.stop();
    activeAnimation = group;
    group.onAnimationGroupEndObservable.addOnce(() => {
      if (activeAnimation === group) activeAnimation = null;
      onEnd?.();
    });
    group.start(false, 1, group.from, group.to, false);
    return true;
  }

  function beginJumpAnimation() {
    jumpAnimationPhase = 'takeoff';
    playJumpClip(jumpStartAnimation, () => {
      if (jumpAnimationPhase !== 'takeoff') return;
      jumpAnimationPhase = 'airborne';
      if (jumpAirAnimation) setAnimation(jumpAirAnimation);
    });
    if (!jumpStartAnimation) {
      jumpAnimationPhase = 'airborne';
      if (jumpAirAnimation) setAnimation(jumpAirAnimation);
    }
  }

  function beginLandingAnimation() {
    jumpAnimationPhase = 'landing';
    playJumpClip(jumpLandAnimation, () => { jumpAnimationPhase = 'grounded'; });
    if (!jumpLandAnimation) jumpAnimationPhase = 'grounded';
  }

  function makeCharacterMaterial() {
    const material = new BABYLON.StandardMaterial('core-player-tint', scene);
    if (window.CORE_APPEARANCE) window.CORE_APPEARANCE.applyToMaterial(material, characterAppearance);
    else material.diffuseColor = BABYLON.Color3.FromHexString(characterAppearance.tint);
    material.specularColor = new BABYLON.Color3(.18, .2, .22);
    material.specularPower = 24;
    // A restrained emissive component keeps the character readable in shadow
    // without replacing the planet's day/night lighting model.
    material.emissiveColor = material.diffuseColor.scale(.18);
    material.ambientColor = new BABYLON.Color3(.24, .27, .31);
    return material;
  }

  function createFallbackCharacter(material) {
    const root = new BABYLON.TransformNode('test-player-root', scene);
    const body = BABYLON.MeshBuilder.CreateCapsule('test-player-body', { height: 1.45, radius: .3, tessellation: 12 }, scene);
    body.position.y = .9;
    body.material = material;
    body.parent = root;
    const head = BABYLON.MeshBuilder.CreateSphere('test-player-head', { diameter: .42, segments: 16 }, scene);
    head.position.y = 1.82;
    head.material = material;
    head.parent = root;
    playerRoot = root;
  }

  async function loadCharacter() {
    const modelDirectory = new URL('../assets/models/characters/', window.location.href).href;
    const result = await BABYLON.SceneLoader.ImportMeshAsync(null, modelDirectory, 'core-humanoid.glb', scene);
    const importedNodes = [...(result.transformNodes || []), ...(result.meshes || [])];
    const importedRoots = [...new Set(importedNodes)].filter(node => !node.parent);
    if (!importedRoots.length) throw new Error('The humanoid model did not contain a usable root node.');

    playerRoot = new BABYLON.TransformNode('test-player-root', scene);
    // Keep the movement root exactly on the sampled terrain point. The visual
    // model gets its own local-space transform so its feet can be placed at
    // local Y=0 and remain grounded after the root is rotated onto a sphere.
    playerVisualRoot = new BABYLON.TransformNode('test-player-visual-root', scene);
    playerVisualRoot.parent = playerRoot;
    importedRoots.forEach(node => { node.parent = playerVisualRoot; });
    const material = makeCharacterMaterial();
    result.meshes.forEach(mesh => {
      if (mesh.geometry && mesh.getTotalVertices() > 0) {
        mesh.material = material;
        mesh.isPickable = false;
      }
    });
    // Imported GLB roots are not guaranteed to expose a usable aggregate
    // hierarchy bound (for example, a transform-only or partially loaded node).
    // Normalize only when both the transform and finite bounds are available.
    if (!playerRoot.position) playerRoot.position = BABYLON.Vector3.Zero();
    if (!playerVisualRoot.position) playerVisualRoot.position = BABYLON.Vector3.Zero();
    if (!playerVisualRoot.scaling) playerVisualRoot.scaling = BABYLON.Vector3.One();
    playerRoot.computeWorldMatrix(true);
    playerVisualRoot.computeWorldMatrix(true);
    let bounds = playerVisualRoot.getHierarchyBoundingVectors(true);
    const validBounds = bounds?.min && bounds?.max
      && [bounds.min.x, bounds.min.y, bounds.min.z, bounds.max.x, bounds.max.y, bounds.max.z].every(Number.isFinite);
    if (validBounds) {
      const height = Math.max(.001, bounds.max.y - bounds.min.y);
      const scale = 1.8 / height;
      playerVisualRoot.scaling = playerVisualRoot.scaling.multiplyByFloats(scale, scale, scale);
      playerVisualRoot.computeWorldMatrix(true);
      bounds = playerVisualRoot.getHierarchyBoundingVectors(true);
      if (bounds?.min && bounds?.max) {
        // Apply the feet/centering correction in the model's local up frame.
        // A world-Y offset is incorrect on a spherical world except at the poles.
        playerVisualRoot.position.y -= Number.isFinite(bounds.min.y) ? bounds.min.y : 0;
        playerVisualRoot.position.x -= Number.isFinite(bounds.min.x + bounds.max.x)
          ? (bounds.min.x + bounds.max.x) / 2 : 0;
        playerVisualRoot.position.z -= Number.isFinite(bounds.min.z + bounds.max.z)
          ? (bounds.min.z + bounds.max.z) / 2 : 0;
      }
    } else {
      console.warn('CORE humanoid model has no finite aggregate bounds; retaining its imported transform.');
    }
    playerRoot.computeWorldMatrix(true);
    playerVisualRoot.computeWorldMatrix(true);

    // Preserve every imported clip by its exact GLB name. Do not use broad
    // /walk/i matching: it selected Crouch_Walk before the intended Walk clip.
    const animationGroups = result.animationGroups || [];
    const byName = new Map(animationGroups.map(group => [String(group.name || '').toLowerCase(), group]));
    const findClip = (...names) => {
      for (const name of names) {
        const match = byName.get(name.toLowerCase());
        if (match) return match;
      }
      return null;
    };
    walkingAnimation = findClip('Walk');
    runningAnimation = findClip('Run_Anime', 'Jog', 'Sprint', 'Run_Female') || walkingAnimation;
    idleAnimation = findClip('Idle_A', 'Idle_Subtle', 'Rest_Pose', 'Idle_Listening');
    jumpStartAnimation = findClip('Jump_Start');
    jumpAirAnimation = findClip('Jump_air', 'Jump_2');
    jumpLandAnimation = findClip('Jump_Land');

    // Expose the complete imported animation library for future gameplay systems
    // and diagnostics, while retaining the exact clip names and group references.
    window.CORE_ANIMATION_CATALOG = animationGroups.map(group => ({
      name: group.name,
      from: group.from,
      to: group.to,
      targetedAnimations: (group.targetedAnimations || []).length
    }));
    window.CORE_PLAY_ANIMATION = (name, loop = false) => {
      const group = byName.get(String(name || '').toLowerCase());
      if (!group) return false;
      setAnimation(group);
      if (!loop) group.onAnimationGroupEndObservable.addOnce(() => {
        setAnimation(idleAnimation || createProceduralIdleAnimation(result.skeletons || []));
      });
      return true;
    };

    result.animationGroups.forEach(group => group.stop());
    if (!walkingAnimation) console.warn('CORE humanoid GLB does not contain the required exact Walk animation.');
    if (!idleAnimation) idleAnimation = createProceduralIdleAnimation(result.skeletons || []);
    if (!jumpStartAnimation || !jumpAirAnimation || !jumpLandAnimation) {
      console.warn('CORE humanoid GLB is missing one or more jump animation clips.', {
        start: !!jumpStartAnimation, air: !!jumpAirAnimation, land: !!jumpLandAnimation
      });
    }
  }

  function setMultiplayerStatus(message) {
    if (multiplayerStatus) multiplayerStatus.textContent = message;
  }

  function refreshMultiplayerStatus() {
    const others = remotePlayers.size;
    setMultiplayerStatus(`Shared world connected · ${others} other player${others === 1 ? '' : 's'} present · server-authoritative movement`);
  }

  function normalizeTransform(value) {
    if (!value || typeof value !== 'object') return null;
    const number = (candidate, fallback = 0) => Number.isFinite(Number(candidate)) ? Number(candidate) : fallback;
    return {
      x: number(value.x), y: number(value.y), z: number(value.z),
      rotationY: number(value.rotationY)
    };
  }

  function shortestAngle(from, to) {
    return Math.atan2(Math.sin(to - from), Math.cos(to - from));
  }

  function remoteTint(value) {
    return /^#[0-9a-f]{6}$/i.test(String(value || '')) ? String(value).toLowerCase() : '#61d8e8';
  }

  function disposeRemotePlayer(remote) {
    if (!remote) return;
    (remote.animations || []).forEach(group => group.dispose());
    if (remote.labelMaterial) remote.labelMaterial.dispose();
    if (remote.labelTexture) remote.labelTexture.dispose();
    if (remote.root) remote.root.dispose(false, true);
  }

  function createRemoteNameplate(root, displayName, remote) {
    const texture = new BABYLON.DynamicTexture(`remote-name-${remote.sessionId}`, { width: 512, height: 128 }, scene, true);
    texture.hasAlpha = true;
    const context = texture.getContext();
    context.clearRect(0, 0, 512, 128);
    context.font = 'bold 46px Arial';
    context.textAlign = 'center';
    context.textBaseline = 'middle';
    context.fillStyle = '#f4f8fc';
    context.shadowColor = '#07111d';
    context.shadowBlur = 12;
    context.fillText(String(displayName || 'Explorer').slice(0, 32), 256, 64, 480);
    texture.update();

    const plane = BABYLON.MeshBuilder.CreatePlane(`remote-nameplate-${remote.sessionId}`, { width: 2.2, height: .55 }, scene);
    plane.parent = root;
    plane.position.y = 2.25;
    plane.billboardMode = BABYLON.Mesh.BILLBOARDMODE_Y;
    plane.isPickable = false;
    const material = new BABYLON.StandardMaterial(`remote-nameplate-material-${remote.sessionId}`, scene);
    material.diffuseTexture = texture;
    material.opacityTexture = texture;
    material.useAlphaFromDiffuseTexture = true;
    material.disableLighting = true;
    material.emissiveColor = new BABYLON.Color3(1, 1, 1);
    material.backFaceCulling = false;
    plane.material = material;
    remote.labelTexture = texture;
    remote.labelMaterial = material;
  }

  async function createRemoteAvatar(remote, snapshot) {
    if (remote.loading || remote.root) return;
    remote.loading = true;
    let root = null;
    try {
      const modelDirectory = new URL('../assets/models/characters/', window.location.href).href;
      const result = await BABYLON.SceneLoader.ImportMeshAsync(null, modelDirectory, 'core-humanoid.glb', scene);
      const importedNodes = [...(result.transformNodes || []), ...(result.meshes || [])];
      const importedRoots = [...new Set(importedNodes)].filter(node => !node.parent);
      if (!importedRoots.length) throw new Error('Remote humanoid model has no root node.');

      root = new BABYLON.TransformNode(`remote-player-${remote.sessionId}`, scene);
      importedRoots.forEach(node => { node.parent = root; });
      const material = new BABYLON.StandardMaterial(`remote-player-material-${remote.sessionId}`, scene);
      const remoteAppearance = window.CORE_APPEARANCE ? window.CORE_APPEARANCE.normalize({ tint: snapshot.appearanceTint, saturation: snapshot.appearanceSaturation, brightness: snapshot.appearanceBrightness }) : { tint: remoteTint(snapshot.appearanceTint) };
      if (window.CORE_APPEARANCE) window.CORE_APPEARANCE.applyToMaterial(material, remoteAppearance);
      else material.diffuseColor = BABYLON.Color3.FromHexString(remoteAppearance.tint);
      material.specularColor = new BABYLON.Color3(.18, .2, .22);
      material.specularPower = 24;
      result.meshes.forEach(mesh => {
        if (mesh.geometry && mesh.getTotalVertices() > 0) {
          mesh.material = material;
          mesh.isPickable = false;
        }
      });
      root.computeWorldMatrix(true);
      let bounds = root.getHierarchyBoundingVectors(true);
      const height = Math.max(.001, bounds.max.y - bounds.min.y);
      const scale = 1.8 / height;
      root.scaling = root.scaling.multiplyByFloats(scale, scale, scale);
      root.computeWorldMatrix(true);
      bounds = root.getHierarchyBoundingVectors(true);
      root.position.y -= bounds.min.y;
      root.position.x -= (bounds.min.x + bounds.max.x) / 2;
      root.position.z -= (bounds.min.z + bounds.max.z) / 2;

      const byName = new Map((result.animationGroups || []).map(group => [String(group.name || '').toLowerCase(), group]));
      remote.animations = result.animationGroups || [];
      remote.walkAnimation = byName.get('walk') || null;
      remote.runAnimation = byName.get('run_anime') || byName.get('jog') || byName.get('sprint') || remote.walkAnimation;
      remote.idleAnimation = byName.get('idle_a') || byName.get('idle_subtle') || byName.get('rest_pose') || byName.get('idle_listening') || null;
      remote.animations.forEach(group => group.stop());
    } catch (error) {
      if (root) root.dispose(false, true);
      root = new BABYLON.TransformNode(`remote-player-${remote.sessionId}`, scene);
      const material = new BABYLON.StandardMaterial(`remote-player-material-${remote.sessionId}`, scene);
      const remoteAppearance = window.CORE_APPEARANCE ? window.CORE_APPEARANCE.normalize({ tint: snapshot.appearanceTint, saturation: snapshot.appearanceSaturation, brightness: snapshot.appearanceBrightness }) : { tint: remoteTint(snapshot.appearanceTint) };
      if (window.CORE_APPEARANCE) window.CORE_APPEARANCE.applyToMaterial(material, remoteAppearance);
      else material.diffuseColor = BABYLON.Color3.FromHexString(remoteAppearance.tint);
      material.specularColor = new BABYLON.Color3(.18, .2, .22);
      const body = BABYLON.MeshBuilder.CreateCapsule(`remote-player-body-${remote.sessionId}`, { height: 1.45, radius: .3, tessellation: 12 }, scene);
      body.position.y = .9;
      body.material = material;
      body.parent = root;
      const head = BABYLON.MeshBuilder.CreateSphere(`remote-player-head-${remote.sessionId}`, { diameter: .42, segments: 16 }, scene);
      head.position.y = 1.82;
      head.material = material;
      head.parent = root;
      remote.animations = [];
      console.warn('CORE remote humanoid failed to load; using a primitive avatar.', error);
    }

    if (!remotePlayers.has(remote.sessionId) || remotePlayers.get(remote.sessionId) !== remote || disposed) {
      remote.animations?.forEach(group => group.dispose());
      root.dispose(false, true);
      remote.loading = false;
      return;
    }
    remote.root = root;
    root.position.copyFrom(window.CORE_PLANETARY_ENVIRONMENT.fromLocalCoordinates(remote.current.x, remote.current.z));
    root.rotationQuaternion = alignUpToSurface(window.CORE_PLANETARY_ENVIRONMENT.getSurfaceNormal(root.position));
    createRemoteNameplate(root, remote.displayName, remote);
    setRemoteAnimation(remote, false);
    remote.loading = false;
  }

  function setRemoteAnimation(remote, moving) {
    if (!remote?.root) return;
    const next = moving
      ? (remote.running ? (remote.runAnimation || remote.walkAnimation) : (remote.walkAnimation || remote.runAnimation))
      : remote.idleAnimation;
    if (remote.activeAnimation === next) return;
    if (remote.activeAnimation) remote.activeAnimation.stop();
    remote.activeAnimation = next || null;
    if (next) next.start(true, 1, next.from, next.to, false);
  }

  function applyPlayerSnapshot(player) {
    if (!player || typeof player !== 'object') return;
    const transform = normalizeTransform(player.transform);
    if (!transform) return;
    if (player.sessionId === multiplayerRoom?.sessionId) {
      authoritativeTransform = transform;
      return;
    }
    if (typeof player.sessionId !== 'string' || !player.sessionId) return;
    let remote = remotePlayers.get(player.sessionId);
    if (!remote) {
      remote = {
        sessionId: player.sessionId,
        displayName: String(player.displayName || 'Explorer').slice(0, 32),
        current: { ...transform },
        target: { ...transform },
        lastUpdateAt: performance.now(),
        running: player.running === true,
        root: null,
        loading: false,
        animations: []
      };
      remotePlayers.set(player.sessionId, remote);
      void createRemoteAvatar(remote, player);
    } else {
      remote.target = transform;
      remote.lastUpdateAt = performance.now();
      remote.running = player.running === true;
      const nextName = String(player.displayName || remote.displayName).slice(0, 32);
      if (nextName !== remote.displayName && remote.root) {
        remote.displayName = nextName;
        // Names are immutable for a connected character in this release.
      }
    }
  }

  function updateRemotePlayers(delta) {
    const blend = 1 - Math.exp(-Math.max(0, delta) * 14);
    const now = performance.now();
    for (const remote of remotePlayers.values()) {
      const dx = remote.target.x - remote.current.x;
      const dy = remote.target.y - remote.current.y;
      const dz = remote.target.z - remote.current.z;
      const moving = Math.hypot(dx, dy, dz) > .025 && now - remote.lastUpdateAt < 350;
      remote.current.x += dx * blend;
      remote.current.y += dy * blend;
      remote.current.z += dz * blend;
      remote.current.rotationY += shortestAngle(remote.current.rotationY, remote.target.rotationY) * blend;
      if (remote.root) {
        remote.root.position.copyFrom(window.CORE_PLANETARY_ENVIRONMENT.fromLocalCoordinates(remote.current.x, remote.current.z));
        remote.root.rotationQuaternion = alignUpToSurface(window.CORE_PLANETARY_ENVIRONMENT.getSurfaceNormal(remote.root.position));
      }
      if (remote.wasMoving !== moving || remote.wasRunning !== remote.running) {
        remote.wasMoving = moving;
        remote.wasRunning = remote.running;
        setRemoteAnimation(remote, moving);
      }
    }
  }

  async function connectWorldMultiplayer() {
    if (!multiplayerUrl) {
      setMultiplayerStatus('Offline session · movement is local only. Configure CORE_MULTIPLAYER_PUBLIC_URL to join shared worlds.');
      return;
    }
    if (!window.Colyseus?.Client) {
      setMultiplayerStatus(window.CORE_COLYSEUS_SDK_ERROR
        ? 'Shared world unavailable · Colyseus browser SDK could not load.'
        : 'Shared world unavailable · waiting for Colyseus browser SDK.');
      return;
    }
    try {
      setMultiplayerStatus('Requesting secure shared-world authorization…');
      const response = await fetch(`${String(boot.apiRoot || '').replace(/\/$/, '')}/api/multiplayer-token.php`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CORE-CSRF': document.body.dataset.csrfToken || ''
        },
        body: JSON.stringify({ character_id: boot.character.id, world_id: boot.world.id }),
        signal: AbortSignal.timeout(12000)
      });
      const raw = await response.text();
      let grant;
      try { grant = JSON.parse(raw); } catch { throw new Error(`Authorization endpoint returned non-JSON data (HTTP ${response.status}).`); }
      if (!response.ok || grant.ok === false || !grant.token) throw new Error(grant.error?.message || `Authorization failed (HTTP ${response.status}).`);
      const identity = grant.identity || {};
      if (identity.characterId !== boot.character.id || identity.universeId !== boot.universeId
        || identity.systemId !== boot.system.id || identity.locationId !== boot.world.id) {
        throw new Error('PHP returned authorization for a different character, system, or world.');
      }
      multiplayerClient = new window.Colyseus.Client(multiplayerUrl);
      multiplayerClient.auth.token = grant.token;
      multiplayerRoom = await multiplayerClient.joinOrCreate('universe', {
        universeId: boot.universeId,
        systemId: boot.system.id,
        locationId: boot.world.id,
        displayName: boot.character.name || 'Explorer'
      });
      multiplayerRoom.onMessage('world-state', message => {
        for (const remote of remotePlayers.values()) disposeRemotePlayer(remote);
        remotePlayers.clear();
        (Array.isArray(message?.players) ? message.players : []).forEach(applyPlayerSnapshot);
        refreshMultiplayerStatus();
      });
      multiplayerRoom.onMessage('player-joined', applyPlayerSnapshot);
      multiplayerRoom.onMessage('player-left', message => {
        if (message?.sessionId) {
          const remote = remotePlayers.get(message.sessionId);
          disposeRemotePlayer(remote);
          remotePlayers.delete(message.sessionId);
        }
        refreshMultiplayerStatus();
      });
      multiplayerRoom.onMessage('player-transforms', message => {
        (Array.isArray(message?.players) ? message.players : []).forEach(applyPlayerSnapshot);
        refreshMultiplayerStatus();
      });
      multiplayerRoom.onLeave(() => {
        multiplayerRoom = null;
        for (const remote of remotePlayers.values()) disposeRemotePlayer(remote);
        remotePlayers.clear();
        setMultiplayerStatus('Shared-world connection ended · movement is now local only.');
      });
      multiplayerRoom.onError((code, message) => setMultiplayerStatus(`Shared-world server error ${code}: ${message || 'connection error'}`));
      refreshMultiplayerStatus();
    } catch (error) {
      multiplayerRoom = null;
      setMultiplayerStatus(`Shared world unavailable · ${error.message || error}`);
    }
  }

  function initializeScene() {
    engine = new BABYLON.Engine(canvas, true, {
      preserveDrawingBuffer: false,
      stencil: true,
      adaptToDeviceRatio: true
    });
    scene = new BABYLON.Scene(engine);
    window.CORE_DIAGNOSTICS?.captureScene(scene, 'planetary-world');
    const initialSky = new BABYLON.Color3(.015, .02, .045);
    scene.clearColor = new BABYLON.Color4(initialSky.r, initialSky.g, initialSky.b, 1);
    scene.fogMode = BABYLON.Scene.FOGMODE_EXP2;
    scene.fogColor = initialSky;
    scene.fogDensity = .0022;

    const camera = new BABYLON.ArcRotateCamera(
      'test-world-camera', -Math.PI / 2, Math.PI / 2.65, 7.5,
      BABYLON.Vector3.Zero(), scene
    );
    camera.lowerRadiusLimit = .25;
    camera.upperRadiusLimit = 24;
    camera.lowerBetaLimit = .08;
    camera.upperBetaLimit = Math.PI - .08;
    // Keep the far plane comfortably beyond the planetary surface horizon.
    // The local terrain mesh now extends beyond the expected horizon, so the
    // far plane must not become the limiting factor at elevated camera views.
    camera.maxZ = Math.max(250000, window.CORE_PLANETARY_ENVIRONMENT.radius * 48);
    camera.fov = Math.max(camera.fov, 1.3);
    camera.minZ = .1;
    camera.wheelDeltaPercentage = .012;
    camera.panningSensibility = 0;
    camera.useBouncingBehavior = false;
    camera.keysUp = [];
    camera.keysDown = [];
    camera.keysLeft = [];
    camera.keysRight = [];
    camera.attachControl(canvas, true);

    window.CORE_PLANETARY_ENVIRONMENT.create(scene);
    if (!window.CORE_ASTRONOMICAL_ENVIRONMENT) {
      throw new Error('The astronomical environment could not load.');
    }
    window.CORE_ASTRONOMICAL_ENVIRONMENT.create(
      scene, boot, camera.getTarget().subtract(camera.position).normalize()
    );

    return camera;
  }

  async function start() {
    let camera;
    try {
      await window.CORE_PLANETARY_ENVIRONMENT.load(boot);
      camera = initializeScene();
      try {
        await loadCharacter();
      } catch (modelError) {
        console.warn('CORE test-world humanoid failed to load; using a primitive character.', modelError);
        createFallbackCharacter(makeCharacterMaterial());
        idleAnimation = createProceduralIdleAnimation([]);
      }
      if (!playerRoot) throw new Error('The character could not be spawned.');
      playerRoot.position.copyFrom(window.CORE_PLANETARY_ENVIRONMENT.spawnPosition);
      groundPosition = playerRoot.position.clone();
      const initialNormal = window.CORE_PLANETARY_ENVIRONMENT.getSurfaceNormal(groundPosition);
      // Establish a deterministic eastward tangent heading at the equatorial
      // spawn (0° latitude, 0° longitude). The camera is elevated and angled
      // for visibility; using its view vector here can project onto the local
      // north/south axis, making ordinary forward travel appear to change
      // latitude only. Subsequent A/D turns remain relative to the local
      // surface normal, so the player can travel in every surface direction.
      let facingDirection = window.CORE_PLANETARY_ENVIRONMENT.getEastTangent(playerRoot.position);
      if (facingDirection.lengthSquared() < 0.0001) {
        facingDirection = camera.getDirection(BABYLON.Axis.Z);
        facingDirection.subtractInPlace(initialNormal.scale(BABYLON.Vector3.Dot(facingDirection, initialNormal)));
      }
      if (facingDirection.lengthSquared() < 0.0001) facingDirection = new BABYLON.Vector3(0, 0, 1);
      facingDirection.normalize();
      playerRoot.rotationQuaternion = orientCharacterToSurface(window.CORE_PLANETARY_ENVIRONMENT.getSurfaceNormal(groundPosition), facingDirection);
      setAnimation(idleAnimation);
      let cameraSurfaceNormal = window.CORE_PLANETARY_ENVIRONMENT.getSurfaceNormal(groundPosition);
      let cameraTarget = playerRoot.position.add(cameraSurfaceNormal.scale(1.05));
      positionCameraAboveSurface(camera, cameraTarget, cameraSurfaceNormal);
      canvas.focus({ preventScroll: true });
      await connectWorldMultiplayer();
      if (multiplayerRoom) {
        const initialLocal = window.CORE_PLANETARY_ENVIRONMENT.toLocalCoordinates(groundPosition);
        authoritativeTransform = { x: initialLocal.x, y: 0, z: initialLocal.z, rotationY: 0 };
        multiplayerRoom.send('set-position', { x: initialLocal.x, z: initialLocal.z });
      }
      currentHex = window.CORE_PLANETARY_ENVIRONMENT.getHexAt(groundPosition.x, groundPosition.z, groundPosition);
      buildWorldMap();

      let cameraMode = 'thirdPerson';
      const thirdPersonRadius = camera.radius;
      const setCameraMode = (mode, preserveRadius = false) => {
        if (!['thirdPerson', 'close', 'firstPerson'].includes(mode)) return;
        cameraMode = mode;
        const normal = window.CORE_PLANETARY_ENVIRONMENT.getSurfaceNormal(groundPosition);
        const targetHeight = mode === 'firstPerson' ? 1.62 : 1.05;
        cameraTarget = playerRoot.position.add(normal.scale(targetHeight));
        if (mode === 'firstPerson') cameraTarget.addInPlace(facingDirection.scale(.25));
        cameraSurfaceNormal = normal.clone();
        camera._coreFirstPersonMode = mode === 'firstPerson';
        playerRoot.setEnabled(mode !== 'firstPerson');
        camera.upVector = normal.clone();
        camera.setTarget(cameraTarget);
        if (mode === 'firstPerson') {
          camera.lowerRadiusLimit = .25;
          camera.upperRadiusLimit = 24;
          camera.radius = .25;
        } else {
          camera.lowerRadiusLimit = 2.4;
          camera.upperRadiusLimit = 24;
          camera.radius = preserveRadius
            ? Math.max(2.55, Math.min(24, camera.radius))
            : (mode === 'close' ? 2.55 : Math.max(7.5, thirdPersonRadius));
          keepCameraAboveTerrain(camera);
        }
      };
      const onKeyDown = event => {
        const key = String(event.key || '').toLowerCase();
        const target = event.target;
        if (developerFlyMode && (key === 'pageup' || key === 'pagedown')) { pressed.add(key); event.preventDefault(); return; }
        // Browser/OS shortcuts (Ctrl+C, Ctrl+V, Cmd+C, Alt+Tab, etc.) must
        // never be interpreted as unmodified gameplay keys.
        if (event.ctrlKey || event.metaKey || event.altKey) return;
        if (target && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName))) return;
        if (event.code === 'F6' || key === 'f6') {
          event.preventDefault();
          if (event.repeat) return;
          developerFlyMode = !developerFlyMode;
          if (!developerFlyMode) {
            // Land at the surface directly beneath the current fly-camera
            // position; never restore the avatar's pre-flight coordinates.
            const landing = window.CORE_PLANETARY_ENVIRONMENT.getSurfacePosition(camera.position);
            groundPosition.copyFrom(landing);
            playerRoot.position.copyFrom(landing);
            const landedNormal = window.CORE_PLANETARY_ENVIRONMENT.getSurfaceNormal(landing);
            facingDirection = camera.getDirection(BABYLON.Axis.Z);
            facingDirection.subtractInPlace(landedNormal.scale(BABYLON.Vector3.Dot(facingDirection, landedNormal)));
            if (facingDirection.lengthSquared() < 0.0001) facingDirection = window.CORE_PLANETARY_ENVIRONMENT.getEastTangent(landing);
            facingDirection.normalize();
            playerRoot.rotationQuaternion = orientCharacterToSurface(landedNormal, facingDirection);
            cameraMode = 'thirdPerson';
            playerRoot.setEnabled(true);
            camera._coreFirstPersonMode = false;
            camera.lowerRadiusLimit = 2.4;
            camera.upperRadiusLimit = 24;
            camera.radius = Math.max(7.5, Math.min(24, camera.radius));
            cameraTarget = landing.add(landedNormal.scale(1.05));
            cameraSurfaceNormal = landedNormal.clone();
            positionCameraAboveSurface(camera, cameraTarget, landedNormal);
            window.CORE_PLANETARY_ENVIRONMENT.updateLocalPatch(landing, true);
            if (multiplayerRoom) {
              const local = window.CORE_PLANETARY_ENVIRONMENT.toLocalCoordinates(landing);
              multiplayerRoom.send('set-position', { x: local.x, z: local.z, reason: 'flight-landing' });
              authoritativeTransform = { x: local.x, y: 0, z: local.z, rotationY: 0 };
            }
          }
          pressed.clear();
          playerRoot.setEnabled(!developerFlyMode);
          if (motionNode) motionNode.textContent = developerFlyMode ? 'Developer fly mode · F6 to return' : 'Standing by';
          return;
        }
        if (developerFlyMode) {
          if (['w', 'a', 's', 'd', 'arrowup', 'arrowdown', 'arrowleft', 'arrowright', 'pageup', 'pagedown'].includes(key)) {
            event.preventDefault();
            pressed.add(key);
          }
          return;
        }
        if (key === 'escape') {
          event.preventDefault();
          if (!mapOverlay?.classList.contains('hidden') || !overlay?.classList.contains('hidden')) closePanels();
          else showMainMenu();
          pressed.clear();
          return;
        }
        const timeBackward = key === '[' || event.code === 'BracketLeft';
        const timeForward = key === ']' || event.code === 'BracketRight';
        if (timeBackward || timeForward) {
          event.preventDefault();
          if (event.repeat) return;
          window.CORE_ASTRONOMICAL_ENVIRONMENT?.adjustTimeHours?.(timeBackward ? -1 : 1);
          return;
        }
        if (key === 'f') {
          event.preventDefault();
          if (event.repeat) return;
          const nextMode = cameraMode === 'thirdPerson' ? 'close'
            : (cameraMode === 'close' ? 'firstPerson' : 'thirdPerson');
          setCameraMode(nextMode);
          return;
        }
        if (key === 'm' || key === 'c' || key === 'i' || key === 'v') {
          event.preventDefault();
          if (event.repeat) return;
          pressed.clear();
          if (key === 'm') {
            if (activePanel === 'map' && !mapOverlay?.classList.contains('hidden')) closePanels();
            else openWorldMap();
          } else if (key === 'c') showCharacterInfo();
          else if (key === 'v') showEnvironmentalBriefing();
          else showInventory();
          return;
        }
        // The floating map is informational and must never capture/block movement.
        // Character, inventory, and menu panels remain modal gameplay interfaces.
        if (!overlay?.classList.contains('hidden')) return;
        if (key === ' ') {
          event.preventDefault();
          if (!event.repeat && jumpHeight <= 0.001 && jumpVelocity <= 0) {
            jumpVelocity = jumpImpulse;
            beginJumpAnimation();
          }
          return;
        }
        if (!movementKeys.has(key)) return;
        event.preventDefault();
        pressed.add(key);
      };
      const onKeyUp = event => pressed.delete(event.key.toLowerCase());
      overlay?.addEventListener('click', event => {
        if (event.target.closest('[data-overlay-close]') || event.target.closest('[data-resume]')) { closePanels(); return; }
        const menu = event.target.closest('[data-menu]');
        if (menu) { showMenuSection(menu.dataset.menu); return; }
        if (event.target.closest('[data-menu-back]')) showMainMenu();
      });
      mapOverlay?.addEventListener('click', event => { if (event.target.closest('[data-map-close]')) closePanels(); });
      const onBlur = () => pressed.clear();
      window.addEventListener('keydown', onKeyDown);
      window.addEventListener('keyup', onKeyUp);
      window.addEventListener('blur', onBlur);

      let lastUiUpdate = 0;
      engine.runRenderLoop(() => {
        if (disposed || !scene || scene.isDisposed || !playerRoot) return;
        const now = performance.now();
        const delta = Math.min(.05, Math.max(0, (now - lastFrame) / 1000));
        lastFrame = now;
        if (!groundPosition) groundPosition = playerRoot.position.clone();
        if (!developerFlyMode && multiplayerRoom && authoritativeTransform) {
          const blend = 1 - Math.exp(-delta * 18);
          const authoritativePosition = window.CORE_PLANETARY_ENVIRONMENT.fromLocalCoordinates(authoritativeTransform.x, authoritativeTransform.z);
          groundPosition.x += (authoritativePosition.x - groundPosition.x) * blend;
          groundPosition.y += (authoritativePosition.y - groundPosition.y) * blend;
          groundPosition.z += (authoritativePosition.z - groundPosition.z) * blend;
        }
        updateRemotePlayers(delta);

        if (developerFlyMode) {
          const forward = camera.getDirection(BABYLON.Axis.Z).normalize();
          const right = camera.getDirection(BABYLON.Axis.X).normalize();
          const up = camera.upVector.clone().normalize();
          const travel = BABYLON.Vector3.Zero();
          if (pressed.has('w') || pressed.has('arrowup')) travel.addInPlace(forward);
          if (pressed.has('s') || pressed.has('arrowdown')) travel.subtractInPlace(forward);
          if (pressed.has('d') || pressed.has('arrowright')) travel.addInPlace(right);
          if (pressed.has('a') || pressed.has('arrowleft')) travel.subtractInPlace(right);
          if (pressed.has('pageup')) travel.addInPlace(up);
          if (pressed.has('pagedown')) travel.subtractInPlace(up);
          if (travel.lengthSquared() > 0.0001) {
            travel.normalize().scaleInPlace(flySpeed * delta);
            const target = camera.getTarget().add(travel);
            camera.position.addInPlace(travel);
            camera.setTarget(target);
          }
          window.CORE_PLANETARY_ENVIRONMENT.updateLocalPatch(camera.position);
          window.CORE_PLANETARY_ENVIRONMENT.updateWater(delta);
          window.CORE_ASTRONOMICAL_ENVIRONMENT.update(
            delta, camera.position, camera.position, camera.getTarget().subtract(camera.position).normalize()
          );
          scene.render();
          return;
        }

        const forwardAmount = (pressed.has('w') || pressed.has('arrowup') ? 1 : 0)
          - (pressed.has('s') || pressed.has('arrowdown') ? 1 : 0);
        const turnAmount = (pressed.has('d') || pressed.has('arrowright') ? 1 : 0)
          - (pressed.has('a') || pressed.has('arrowleft') ? 1 : 0);
        const radial = window.CORE_PLANETARY_ENVIRONMENT.getSurfaceNormal(groundPosition);
        // A/D are turning controls, not strafe controls. Rotate the tangent
        // heading around the local surface normal, including while stationary.
        if (turnAmount !== 0) {
          const turnRotation = BABYLON.Matrix.RotationAxis(radial, turnAmount * 2.4 * delta);
          facingDirection = BABYLON.Vector3.TransformNormal(facingDirection, turnRotation);
          facingDirection.subtractInPlace(radial.scale(BABYLON.Vector3.Dot(facingDirection, radial))).normalize();
        }
        const moving = forwardAmount !== 0;
        const direction = facingDirection.clone().scaleInPlace(forwardAmount);
        const running = pressed.has('shift');
        if (multiplayerRoom) {
          if (now - lastInputSent >= 50) {
            let inputForward = 0;
            let inputStrafe = 0;
            if (moving && direction.lengthSquared() > .0001) {
              direction.normalize();
              // The multiplayer server's map plane is X=east / Z=north.
              // World-space XYZ cannot be copied directly: at the equator,
              // east is world +Z and north is world +Y. Project onto the
              // planet's local tangent basis before sending bounded input.
              const localMovement = window.CORE_PLANETARY_ENVIRONMENT.getLocalMovementComponents(groundPosition, direction);
              inputForward = Math.abs(localMovement.north) < .15 ? 0 : Math.sign(localMovement.north);
              inputStrafe = Math.abs(localMovement.east) < .15 ? 0 : Math.sign(localMovement.east);
            }
            multiplayerRoom.send('move', { forward: inputForward, strafe: inputStrafe, running: moving && running, sequence: inputSequence++ });
            lastInputSent = now;
          }
          if (moving) {
            if (jumpHeight <= 0.001 && jumpVelocity <= 0 && jumpAnimationPhase === 'grounded') setAnimation(running ? runningAnimation : walkingAnimation);
            if (motionNode) motionNode.textContent = running ? 'Running · server controlled' : 'Walking · server controlled';
          } else {
            if (jumpHeight <= 0.001 && jumpVelocity <= 0 && jumpAnimationPhase === 'grounded') setAnimation(idleAnimation);
            if (motionNode) motionNode.textContent = jumpHeight > 0.001 ? 'Jumping · server controlled' : 'Standing by · server controlled';
          }
        } else if (moving && direction.lengthSquared() > .0001) {
          direction.normalize();
          const previousNormal = radial.clone();
          groundPosition.copyFrom(window.CORE_PLANETARY_ENVIRONMENT.moveOnSurface(groundPosition, direction, (running ? speedRun : speedWalk) * delta));
          const movedNormal = window.CORE_PLANETARY_ENVIRONMENT.getSurfaceNormal(groundPosition);
          // Transport the heading with the character as it moves over the sphere.
          facingDirection = rotateVectorBetweenNormals(facingDirection, previousNormal, movedNormal).normalize();
          if (jumpHeight <= 0.001 && jumpVelocity <= 0 && jumpAnimationPhase === 'grounded') setAnimation(running ? runningAnimation : walkingAnimation);
          if (motionNode) motionNode.textContent = running ? 'Running · local only' : 'Walking · local only';
        } else {
          if (jumpHeight <= 0.001 && jumpVelocity <= 0 && jumpAnimationPhase === 'grounded') setAnimation(idleAnimation);
          if (motionNode) motionNode.textContent = jumpHeight > 0 ? 'Jumping' : 'Standing by';
        }
        const wasAirborne = jumpHeight > 0.001 || jumpVelocity > 0;
        if (jumpVelocity !== 0 || jumpHeight > 0) {
          jumpHeight = Math.max(0, jumpHeight + jumpVelocity * delta);
          jumpVelocity -= gravityAcceleration * delta;
          if (jumpHeight === 0) jumpVelocity = 0;
        }
        if (wasAirborne && jumpHeight === 0 && jumpVelocity === 0) beginLandingAnimation();
        else if (jumpAnimationPhase === 'takeoff' && jumpVelocity <= 0) {
          // The takeoff clip must not hold the character in that pose for its
          // full authored duration. Switch at the physical apex so the airborne
          // pose is present throughout descent, regardless of clip length.
          jumpAnimationPhase = 'airborne';
          if (jumpAirAnimation) setAnimation(jumpAirAnimation);
        } else if (jumpHeight > 0.001 && jumpAnimationPhase === 'airborne' && activeAnimation !== jumpAirAnimation && jumpAirAnimation) setAnimation(jumpAirAnimation);
        window.CORE_PLANETARY_ENVIRONMENT.updateLocalPatch(groundPosition);
        const surfaceNormal = window.CORE_PLANETARY_ENVIRONMENT.getSurfaceNormal(groundPosition);
        playerRoot.position.copyFrom(groundPosition.add(surfaceNormal.scale(jumpHeight)));
        playerRoot.rotationQuaternion = orientCharacterToSurface(surfaceNormal, facingDirection);
        // Scrolling inward enters first-person automatically. Scrolling outward
        // leaves it before the camera can pass through the terrain surface.
        if (cameraMode !== 'firstPerson' && camera.radius <= 2.45) {
          setCameraMode('firstPerson');
        } else if (cameraMode === 'firstPerson' && camera.radius >= 1.5) {
          setCameraMode('close', true);
        }
        const nextCameraTarget = playerRoot.position.add(surfaceNormal.scale(cameraMode === 'firstPerson' ? 1.62 : 1.05));
        if (cameraMode === 'firstPerson') nextCameraTarget.addInPlace(facingDirection.scale(.25));
        if (BABYLON.Vector3.DistanceSquared(nextCameraTarget, cameraTarget) > 0.000001
          || BABYLON.Vector3.Dot(surfaceNormal, cameraSurfaceNormal) < 0.999999) {
          positionCameraAboveSurface(camera, nextCameraTarget, surfaceNormal, cameraSurfaceNormal);
          cameraTarget = nextCameraTarget;
          cameraSurfaceNormal = surfaceNormal.clone();
        }
        keepCameraAboveTerrain(camera);
        window.CORE_PLANETARY_ENVIRONMENT.updateWater(delta);
        window.CORE_ASTRONOMICAL_ENVIRONMENT.update(
          delta, playerRoot.position, camera.position, camera.getTarget().subtract(camera.position).normalize()
        );
        if (now - lastUiUpdate > 100) {
          currentHex = window.CORE_PLANETARY_ENVIRONMENT.getHexAt(groundPosition.x, groundPosition.z, groundPosition);
          const coordinates = window.CORE_PLANETARY_ENVIRONMENT.getCoordinates(groundPosition);
          if (coordinatesNode) {
            const altitudeKm = window.CORE_PLANETARY_ENVIRONMENT.getAltitude(groundPosition);
            const altitudeMeters = Math.round(altitudeKm * 1000);
            coordinatesNode.textContent = `${coordinates.latitude.toFixed(3)}° · ${coordinates.longitude.toFixed(3)}° · ${altitudeMeters >= 0 ? '+' : ''}${altitudeMeters.toLocaleString()} m`;
          }
          const localTime = window.CORE_ASTRONOMICAL_ENVIRONMENT?.getTimeState?.();
          if (localTimeNode && localTime) localTimeNode.textContent = localTime.time;
          if (dayCountNode && localTime) dayCountNode.textContent = `Day ${localTime.day}`;
          if (dayIconNode && localTime) {
            const icon = localTime.phase === 'day' ? '☀' : (localTime.phase === 'twilight' ? '◐' : '☾');
            dayIconNode.textContent = icon;
            dayIconNode.setAttribute('aria-label', localTime.phase);
            dayIconNode.title = localTime.phase === 'day' ? 'Daylight' : (localTime.phase === 'twilight' ? 'Twilight' : 'Night');
          }
          updateWorldMapMarker();
          lastUiUpdate = now;
        }
        scene.render();
      });

      const resize = () => engine?.resize();
      window.addEventListener('resize', resize);
      window.addEventListener('pagehide', () => {
        disposed = true;
        window.removeEventListener('keydown', onKeyDown);
        window.removeEventListener('keyup', onKeyUp);
        window.removeEventListener('blur', onBlur);
        window.removeEventListener('resize', resize);
        if (multiplayerRoom) multiplayerRoom.leave();
        window.CORE_ASTRONOMICAL_ENVIRONMENT?.dispose();
        if (engine) engine.dispose();
      }, { once: true });
      if (loading) loading.classList.add('hidden');
    } catch (error) {
      console.error('CORE test world initialization failed', error);
      if (engine) engine.dispose();
      showError(`The test world could not be initialized: ${error.message || error}`);
    }
  }

  start();
})();
