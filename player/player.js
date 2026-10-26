(() => {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const apiRoot = document.body.dataset.apiRoot || '';
  const api = (path) => `${apiRoot}/api/${path}`;
  const state = { universe: null, characters: [], character: null, navigation: null, engine: null, scene: null, nodes: new Map(), selectedSystem: null };

  function notice(message, type = '') { const el = $('notice'); el.textContent = message; el.className = `notice ${type}`.trim(); }
  async function request(url, options = {}) {
    const response = await fetch(url, { headers: { 'Accept': 'application/json', ...(options.body ? { 'Content-Type': 'application/json' } : {}) }, ...options });
    let data;
    try { data = await response.json(); } catch { throw new Error(`Server returned an unreadable response (HTTP ${response.status}).`); }
    if (!response.ok || data.ok === false) throw new Error(data.error?.message || `Request failed (HTTP ${response.status}).`);
    return data;
  }
  function systemName(id) { return state.universe?.baseline?.systems?.find(s => s.id === id)?.name || id || 'Unknown'; }
  function currentSystemId() { return state.character?.current_system_id || state.character?.location?.system_id || ''; }

  $('universeForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const seed = $('universeSeed').value.trim();
    if (!seed) return;
    notice('Loading universe from CORE…');
    try {
      const data = await request(api('universe.php'), { method: 'POST', body: JSON.stringify({ seed }) });
      state.universe = data.universe || data.data?.universe || null;
      if (!state.universe?.baseline?.systems || !Array.isArray(state.universe.baseline.systems)) {
        throw new Error('Universe loaded, but the API response did not contain baseline.systems. Check the universe API response.');
      }
      $('gameLayout').classList.remove('hidden');
      $('universeName').textContent = `Universe ${state.universe.id}`;
      $('systemCount').textContent = String(state.universe.baseline?.systems?.length ?? state.universe.baseline?.system_count ?? 0);
      $('seedFingerprint').textContent = state.universe.seed_fingerprint || state.universe.baseline?.seed_fingerprint || '—';
      $('createCharacterButton').disabled = false;
      await loadCharacters();
      await loadNavigation();
      renderMap();
      notice(`Universe loaded. ${state.universe.baseline.systems.length} systems are ready for inspection.`, 'success');
    } catch (error) { notice(error.message, 'error'); }
  });

  async function loadCharacters() {
    const data = await request(api(`characters.php?universe_id=${encodeURIComponent(state.universe.id)}`));
    state.characters = Array.isArray(data.characters) ? data.characters : [];
    const select = $('characterSelect');
    select.replaceChildren();
    if (!state.characters.length) {
      select.add(new Option('No characters yet', ''));
      state.character = null;
    } else {
      select.add(new Option('Select a character…', ''));
      state.characters.forEach(character => select.add(new Option(`${character.name} · ${character.role || 'Adventurer'}`, character.id)));
      const previouslySelected = state.character && state.characters.some(c => c.id === state.character.id) ? state.character.id : state.characters[0].id;
      select.value = previouslySelected;
      state.character = state.characters.find(c => c.id === previouslySelected) || null;
    }
    select.disabled = false;
    updateCharacterPanel();
  }
  async function loadNavigation() {
    const data = await request(api(`navigation.php?universe_id=${encodeURIComponent(state.universe.id)}`));
    state.navigation = data.navigation || data || null;
  }
  function updateCharacterPanel() {
    $('characterLocation').textContent = state.character ? systemName(currentSystemId()) : 'No character selected';
    $('characterSelect').value = state.character?.id || '';
    if (state.character) notice(`Selected ${state.character.name}. Current system: ${systemName(currentSystemId())}.`, 'success');
  }
  $('characterSelect').addEventListener('change', () => {
    state.character = state.characters.find(c => c.id === $('characterSelect').value) || null;
    updateCharacterPanel();
    if (state.character && currentSystemId()) selectSystem(currentSystemId());
    updateTravelButton();
  });
  $('characterForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!state.universe) return;
    const name = $('characterName').value.trim();
    const role = $('characterRole').value;
    if (!name) return;
    $('createCharacterButton').disabled = true;
    notice('Creating character…');
    try {
      const data = await request(api('characters.php'), { method: 'POST', body: JSON.stringify({ universe_id: state.universe.id, name, role }) });
      await loadCharacters();
      state.character = state.characters.find(c => c.id === data.character.id) || data.character;
      $('characterSelect').value = state.character.id;
      $('characterName').value = '';
      updateCharacterPanel();
      updateTravelButton();
      notice(`Character ${state.character.name} created and saved.`, 'success');
      if (currentSystemId()) selectSystem(currentSystemId());
    } catch (error) { notice(error.message, 'error'); }
    finally { $('createCharacterButton').disabled = !state.universe; }
  });

  function updateTravelButton() {
    const button = $('travelButton');
    if (!button) return;
    const enabled = Boolean(state.character && state.selectedSystem && currentSystemId() && state.selectedSystem !== currentSystemId());
    button.disabled = !enabled;
    $('travelHint').textContent = !state.character ? 'Select a character first.' : !state.selectedSystem ? 'Select a destination system on the map.' : state.selectedSystem === currentSystemId() ? 'You are already in this system.' : `Destination: ${systemName(state.selectedSystem)}. Travel follows connected navigation links.`;
  }

  $('travelButton').addEventListener('click', async () => {
    if (!state.character || !state.selectedSystem || !state.universe) return;
    const button = $('travelButton'); button.disabled = true;
    try {
      notice(`Planning route to ${systemName(state.selectedSystem)}…`);
      const routeData = await request(api(`navigation-route.php?universe_id=${encodeURIComponent(state.universe.id)}&from_system_id=${encodeURIComponent(currentSystemId())}&to_system_id=${encodeURIComponent(state.selectedSystem)}`));
      let character = state.character;
      const started = await request(api('character-travel.php'), {method:'POST', body:JSON.stringify({action:'start', universe_id:state.universe.id, character_id:character.id, destination_system_id:state.selectedSystem, expected_revision:Number(character.revision ?? 0)})});
      character = started.character || character;
      const hops = Number(routeData.hops || 0);
      for (let i=0; i<hops; i++) {
        notice(`Travel in progress · jump ${i+1} of ${hops}…`);
        const advanced = await request(api('character-travel.php'), {method:'POST', body:JSON.stringify({action:'advance', universe_id:state.universe.id, character_id:character.id, expected_revision:Number(character.revision ?? 0)})});
        character = advanced.character;
      }
      state.character = character;
      await loadCharacters();
      state.character = state.characters.find(c => c.id === character.id) || character;
      $('characterSelect').value = state.character.id;
      updateCharacterPanel();
      renderMap();
      selectSystem(currentSystemId());
      notice(`Arrived at ${systemName(currentSystemId())}. ${hops} navigation jump(s) completed.`, 'success');
    } catch (error) { notice(error.message, 'error'); }
    finally { updateTravelButton(); }
  });

  function renderMap() {
    const systems = state.universe?.baseline?.systems || [];
    if (!systems.length) {
      $('mapStatus').textContent = 'No star systems were returned by the universe generator.';
      $('canvasFallback').classList.remove('hidden');
      $('canvasFallback').textContent = 'The universe loaded, but it contains no renderable systems.';
      return;
    }
    if (!window.BABYLON) {
      $('canvasFallback').classList.remove('hidden');
      $('canvasFallback').textContent = 'Babylon.js did not load. Check browser internet access or CDN blocking.';
      $('mapStatus').textContent = '3D engine unavailable';
      return;
    }
    const canvas = $('renderCanvas');
    try {
      if (!state.engine) {
        state.engine = new BABYLON.Engine(canvas, true, { preserveDrawingBuffer: true, stencil: true, adaptToDeviceRatio: true });
        state.scene = new BABYLON.Scene(state.engine);
        state.scene.clearColor = new BABYLON.Color4(0.025, 0.045, 0.075, 1);
        const camera = new BABYLON.ArcRotateCamera('map-camera', -Math.PI / 2, Math.PI / 3, 25, BABYLON.Vector3.Zero(), state.scene);
        camera.attachControl(canvas, true);
        camera.lowerRadiusLimit = 3;
        camera.upperRadiusLimit = 150;
        camera.wheelDeltaPercentage = 0.015;
        camera.panningSensibility = 45;
        new BABYLON.HemisphericLight('ambient', new BABYLON.Vector3(0, 1, 0), state.scene).intensity = 1.0;
        const key = new BABYLON.PointLight('star-light', new BABYLON.Vector3(0, 8, 0), state.scene); key.intensity = 0.7;
        // Render continuously, and surface render failures instead of leaving an unexplained blank canvas.
        state.engine.runRenderLoop(() => {
          try { if (state.scene && !state.scene.isDisposed) state.scene.render(); }
          catch (error) { $('mapStatus').textContent = `3D render error: ${error.message || error}`; }
        });
        window.addEventListener('resize', () => state.engine?.resize());
        if ('ResizeObserver' in window) new ResizeObserver(() => state.engine?.resize()).observe(canvas.parentElement);
      }
      $('canvasFallback').classList.add('hidden');
      state.engine.resize();
      state.nodes.forEach(mesh => mesh.dispose()); state.nodes.clear();
      state.scene.meshes.filter(mesh => mesh.name.startsWith('route-') || mesh.name === 'map-grid').forEach(mesh => mesh.dispose());
      const finite = value => Number.isFinite(Number(value)) ? Number(value) : 0;
      const minX = Math.min(...systems.map(s => finite(s.x))), maxX = Math.max(...systems.map(s => finite(s.x)));
      const minY = Math.min(...systems.map(s => finite(s.y))), maxY = Math.max(...systems.map(s => finite(s.y)));
      const span = Math.max(maxX - minX, maxY - minY, 1);
      const scale = 24 / span;
      const coords = new Map();
      systems.forEach(system => coords.set(system.id, new BABYLON.Vector3((finite(system.x) - (minX + maxX) / 2) * scale, 0, (finite(system.y) - (minY + maxY) / 2) * scale)));
      // A subtle ground grid makes it obvious that the 3D scene has initialized, even for sparse universes.
      const grid = BABYLON.MeshBuilder.CreateGround('map-grid', { width: 32, height: 32, subdivisions: 16 }, state.scene);
      const gridMaterial = new BABYLON.StandardMaterial('map-grid-material', state.scene);
      gridMaterial.wireframe = true; gridMaterial.emissiveColor = new BABYLON.Color3(0.06, 0.12, 0.17); gridMaterial.alpha = 0.35;
      grid.material = gridMaterial; grid.isPickable = false; grid.position.y = -0.18;
      const routes = state.navigation?.routes || state.navigation?.edges || [];
      routes.forEach((route, index) => {
        const a = coords.get(route.from), b = coords.get(route.to); if (!a || !b) return;
        const line = BABYLON.MeshBuilder.CreateLines(`route-${index}`, { points: [a, b] }, state.scene);
        line.color = new BABYLON.Color3(0.18, 0.38, 0.49); line.isPickable = false;
      });
      systems.forEach((system, index) => {
        const position = coords.get(system.id);
        const mesh = BABYLON.MeshBuilder.CreateSphere(`system-${index}`, { diameter: 0.8, segments: 12 }, state.scene);
        mesh.position.copyFrom(position); mesh.isPickable = true;
        mesh.metadata = { entityId: system.id, entityType: 'star_system', entityData: system };
        const material = new BABYLON.StandardMaterial(`system-material-${index}`, state.scene);
        material.diffuseColor = new BABYLON.Color3(0.18, 0.68, 0.82); material.emissiveColor = new BABYLON.Color3(0.08, 0.28, 0.38); mesh.material = material;
        state.nodes.set(system.id, mesh);
      });
      state.scene.onPointerObservable.clear();
      state.scene.onPointerObservable.add((pointerInfo) => {
        if (pointerInfo.type !== BABYLON.PointerEventTypes.POINTERPICK || !pointerInfo.pickInfo?.hit) return;
        const mesh = pointerInfo.pickInfo.pickedMesh; const id = mesh?.metadata?.entityId;
        if (id) selectSystem(id);
      });
      const target = currentSystemId();
      const focus = target && coords.has(target) ? coords.get(target) : BABYLON.Vector3.Zero();
      state.scene.activeCamera.setTarget(focus);
      state.scene.activeCamera.radius = Math.max(18, Math.min(70, span * scale * 1.35));
      state.scene.activeCamera.alpha = -Math.PI / 2;
      state.scene.activeCamera.beta = Math.PI / 3;
      state.engine.resize();
      $('mapStatus').textContent = `${systems.length} systems rendered · ${routes.length} navigation links · drag to orbit`;
      if (!state.selectedSystem && systems.length) selectSystem(systems[0].id);
    } catch (error) {
      $('canvasFallback').classList.remove('hidden');
      $('canvasFallback').textContent = `The 3D map could not be rendered: ${error.message || error}`;
      $('mapStatus').textContent = '3D map initialization failed';
    }
  }
  function selectSystem(id) {
    const system = state.universe?.baseline?.systems?.find(item => item.id === id);
    if (!system) return;
    state.selectedSystem = id;
    $('selectedName').textContent = system.name || id;
    $('selectedDescription').textContent = `${system.classification || 'Unclassified'} system. Coordinates: ${system.x}, ${system.y}. This map uses generated system coordinates and the backend navigation graph.`;
    $('selectedId').textContent = id;
    $('selectedClassification').textContent = system.classification || 'Unclassified';
    $('selectedCoordinates').textContent = `${system.x}, ${system.y}`;
    $('selectedWorlds').textContent = Array.isArray(system.worlds) ? String(system.worlds.length) : 'Not generated yet';
    updateTravelButton();
    state.nodes.forEach((mesh, systemId) => {
      const material = mesh.material;
      if (material) { material.emissiveColor = systemId === id ? new BABYLON.Color3(0.2, 0.12, 0.02) : new BABYLON.Color3(0.04, 0.18, 0.24); material.diffuseColor = systemId === id ? new BABYLON.Color3(1, 0.72, 0.26) : new BABYLON.Color3(0.18, 0.68, 0.82); }
    });
  }
})();
