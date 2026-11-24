(() => {
  'use strict';
  window.CORE_PLAYER_SCRIPT_LOADED = true;
  const $ = id => document.getElementById(id);
  window.addEventListener('error', event => {
    const el = $('notice');
    if (el) { el.textContent = `Gameplay JavaScript error: ${event.message || 'unknown error'}`; el.className = 'notice error'; }
  });
  window.addEventListener('unhandledrejection', event => {
    const el = $('notice');
    if (el) { const reason = event.reason; el.textContent = `Gameplay request failed: ${reason?.message || String(reason || 'unknown error')}`; el.className = 'notice error'; }
  });
  const apiRoot = document.body.dataset.apiRoot || '';
  const api = path => `${apiRoot}/api/${path}`;
  const boot = window.CORE_BOOT || {};
  const state = { universe: boot.universe || null, character: boot.character || null, engine: null, scene: null, nodes: new Map(), navigation: null, view: 'universe', universeCamera: null, selectedConstellationId: boot.currentConstellationId || '', selectedSystem: null, selectedWorld: null, selectedWorldId: null, resizeObserver: null, lastCanvasSize: null, dragStart: null, dragged: false, multiplayerClient: null, multiplayerRoom: null, multiplayerConnecting: false };
  window.CORE_GAME_STATE = state;
  const notice = (message, type='') => { const el=$('notice'); if(el){el.textContent=message;el.className=`notice ${type}`.trim();} };
  async function request(url, options={}) {
    const method = String(options.method || 'GET').toUpperCase();
    const headers = {Accept:'application/json', ...(options.body?{'Content-Type':'application/json'}:{}), ...(options.headers || {})};
    if (method !== 'GET') headers['X-CORE-CSRF'] = document.body.dataset.csrfToken || '';
    const response = await fetch(url, {signal: AbortSignal.timeout(12000), ...options, method, headers});
    const raw = await response.text(); let data;
    try { data = JSON.parse(raw); } catch { throw new Error(`API ${url} returned non-JSON data (HTTP ${response.status}). ${raw.slice(0,180)}`); }
    if (!response.ok || data.ok === false) throw new Error(data.error?.message || `Request failed (HTTP ${response.status}).`);
    return data;
  }
  const systems = () => state.universe?.baseline?.systems || [];
  const constellations = () => state.universe?.baseline?.constellations || [];
  const systemName = id => systems().find(s=>s.id===id)?.name || id || 'Unknown';
  const constellationName = id => constellations().find(c=>c.id===id)?.name || id || 'Unknown';
  const visibleSystems = () => systems().filter(system => !state.selectedConstellationId || system.constellation_id === state.selectedConstellationId);
  const normalizeEntityId = value => String(value ?? '').trim();
  function createMapLabel(name, subtitle, position, width, height, scene, meshName) {
    const texture = new BABYLON.DynamicTexture(`${meshName}-texture`, { width: 512, height: 128 }, scene, false);
    texture.hasAlpha = true;
    // Babylon plane UVs invert the canvas texture vertically in this view;
    // explicitly flip V so the label reads upright from the map camera.
    texture.vScale = -1;
    texture.vOffset = 1;
    const ctx = texture.getContext();
    ctx.clearRect(0, 0, 512, 128);
    ctx.font = 'bold 36px Arial'; ctx.textAlign = 'center'; ctx.fillStyle = '#f1f8ff';
    ctx.shadowColor = '#07111d'; ctx.shadowBlur = 9;
    ctx.fillText(String(name || ''), 256, 52);
    if (subtitle) {
      ctx.font = '24px Arial'; ctx.fillStyle = '#a9c4d7';
      ctx.fillText(String(subtitle), 256, 94);
    }
    texture.update(false);
    const label = BABYLON.MeshBuilder.CreatePlane(meshName, { width, height }, scene);
    label.position.copyFrom(position);
    label.billboardMode = BABYLON.Mesh.BILLBOARDMODE_ALL;
    const material = new BABYLON.StandardMaterial(`${meshName}-material`, scene);
    material.diffuseTexture = texture;
    material.opacityTexture = texture;
    material.emissiveColor = new BABYLON.Color3(1, 1, 1);
    material.disableLighting = true;
    material.backFaceCulling = false;
    material.disableDepthWrite = true;
    material.depthFunction = BABYLON.Engine.ALWAYS;
    label.material = material;
    label.isPickable = false;
    label.renderingGroupId = 2;
    return label;
  }
  const currentSystemId = () => {
    // Prefer the character's canonical location, but do not let a blank, whitespace,
    // or stale legacy field mask a valid location stored in the nested location object.
    const candidates = [
      state.character?.current_system_id,
      state.character?.location?.system_id,
      boot.initialSystemId
    ].map(normalizeEntityId).filter(Boolean);
    const known = candidates.find(id => systems().some(system => normalizeEntityId(system.id) === id));
    return known || candidates[0] || '';
  };
  const currentSystemRecord = () => systems().find(system => normalizeEntityId(system.id) === currentSystemId()) || null;
  function selectedWorldEntryContext() {
    const currentSystem = currentSystemRecord();
    const activeSystemId = normalizeEntityId(
      state.view === 'system' ? (window.CORE_SYSTEM_VIEW?.activeSystemId || state.selectedSystem) : state.selectedSystem
    );
    const selectedSystem = systems().find(system => normalizeEntityId(system.id) === activeSystemId) || null;
    if (!currentSystem) return { ok: false, reason: 'The character current star system could not be resolved.' };
    if (!selectedSystem) return { ok: false, reason: 'Open a star system before selecting a world.' };
    if (normalizeEntityId(selectedSystem.id) !== normalizeEntityId(currentSystem.id)) {
      return { ok: false, reason: 'Enter Test World is available only in the character current star system.' };
    }
    const selectedWorldId = normalizeEntityId(state.selectedWorld?.id);
    if (!selectedWorldId) return { ok: false, reason: 'Select a generated world in this system.' };
    const world = (Array.isArray(selectedSystem.worlds) ? selectedSystem.worlds : [])
      .find(candidate => normalizeEntityId(candidate?.id) === selectedWorldId);
    if (!world || (normalizeEntityId(world.system_id) && normalizeEntityId(world.system_id) !== normalizeEntityId(selectedSystem.id))) {
      return { ok: false, reason: 'The selected world does not belong to this star system.' };
    }
    return { ok: true, system: selectedSystem, world };
  }
  function canEnterSelectedWorld() {
    return selectedWorldEntryContext().ok;
  }
  function updateWorldEntryControl() {
    const context = selectedWorldEntryContext();
    const button = $('enterWorldButton');
    const status = $('worldEntryStatus');
    if (button) button.disabled = !context.ok;
    if (status) status.textContent = context.ok
      ? 'Ready to enter this generated World. This is a temporary test environment; world coordinates are not saved yet.'
      : context.reason;
  }
  const currentConstellationId = () => {
    const candidates = [state.character?.current_constellation_id, state.character?.location?.constellation_id]
      .map(normalizeEntityId).filter(Boolean);
    const known = candidates.find(id => constellations().some(item => normalizeEntityId(item.id) === id));
    return known || normalizeEntityId(currentSystemRecord()?.constellation_id) || candidates[0] || '';
  };
  function updateConstellationViewHeading() {
    const selected = constellations().find(item => item.id === state.selectedConstellationId);
    const label = selected?.name || 'Unknown Constellation';
    const heading = $('mapConstellationName');
    if (heading) heading.textContent = label;
    const selector = $('constellationSelect');
    if (selector && selector.value !== state.selectedConstellationId) selector.value = state.selectedConstellationId;
  }
  function selectConstellation(id) {
    const constellation = constellations().find(item => normalizeEntityId(item.id) === normalizeEntityId(id));
    if (!constellation) return;
    state.selectedConstellationId = normalizeEntityId(constellation.id);
    state.selectedSystem = null;
    state.selectedWorld = null;
    state.selectedWorldId = null;
    const relatedSystems = systems().filter(system => normalizeEntityId(system.constellation_id) === state.selectedConstellationId);
    const worldCount = relatedSystems.reduce((total, system) => total + (Array.isArray(system.worlds) ? system.worlds.length : 0), 0);
    const name = constellation.name || constellation.id;
    $('selectedEntityEyebrow').textContent = 'SELECTED CONSTELLATION';
    $('selectedName').textContent = name;
    $('selectedDescription').textContent = `${relatedSystems.length} star systems and ${worldCount} generated worlds. Open this Constellation to inspect its systems and navigation links.`;
    $('selectedStatOneLabel').textContent = 'Star systems';
    $('selectedClassification').textContent = String(relatedSystems.length);
    $('selectedCoordinates').textContent = `${constellation.x ?? '—'}, ${constellation.y ?? '—'}`;
    $('selectedStatThreeLabel').textContent = 'Total worlds';
    $('selectedWorlds').textContent = String(worldCount);
    $('selectedId').textContent = String(constellation.id || '—');
    const button = $('viewSystemButton');
    if (button) { button.disabled = false; button.hidden = false; button.textContent = 'View Selected Constellation'; }
    renderConstellationList();
    state.nodes.forEach((mesh, key) => { if (mesh.material && mesh.metadata?.entityType === 'constellation') mesh.material.emissiveColor = key === state.selectedConstellationId ? new BABYLON.Color3(.42, .31, .08) : new BABYLON.Color3(.06, .27, .39); });
    updateConstellationViewHeading();
  }
  function renderConstellationList() {
    const list = $('constellationSystemList');
    if (!list) return;
    list.replaceChildren();
    const entries = state.view === 'universe' ? constellations() : visibleSystems();
    entries.forEach(entry => {
      const item = document.createElement('li');
      if (state.view === 'universe') {
        item.dataset.constellationId = entry.id;
        item.setAttribute('aria-pressed', entry.id === state.selectedConstellationId ? 'true' : 'false');
        if (entry.id === state.selectedConstellationId) item.classList.add('is-selected');
        const count = systems().filter(system => system.constellation_id === entry.id).length;
        const title = document.createElement('strong'); title.textContent = entry.name || entry.id;
        const detail = document.createElement('span'); detail.textContent = `${count} star systems`;
        item.append(title, detail);
      } else {
        item.dataset.systemId = entry.id;
        item.setAttribute('aria-pressed', entry.id === state.selectedSystem ? 'true' : 'false');
        if (entry.id === state.selectedSystem) item.classList.add('is-selected');
        const title = document.createElement('strong'); title.textContent = entry.name || entry.id;
        const detail = document.createElement('span'); detail.textContent = `${entry.classification || 'Unclassified'} · ${Array.isArray(entry.worlds) ? entry.worlds.length : 0} worlds`;
        item.append(title, detail);
      }
      item.tabIndex = 0; item.setAttribute('role', 'button'); list.append(item);
    });
  }
  function setStatus(message) { $('mapStatus').textContent=message; }
  const multiplayerUrl = String(boot.multiplayerUrl || document.body.dataset.multiplayerUrl || '').trim();
  function setMultiplayerStatus(message, connected=false) {
    const status = $('multiplayerStatus');
    const button = $('multiplayerConnectButton');
    if (status) status.textContent = message;
    if (button) {
      button.disabled = !multiplayerUrl || state.multiplayerConnecting || (!connected && !window.Colyseus?.Client);
      button.textContent = connected ? 'Disconnect' : (state.multiplayerConnecting ? 'Connecting…' : 'Connect');
    }
  }
  async function disconnectMultiplayer(updateStatus=true) {
    const room = state.multiplayerRoom;
    state.multiplayerRoom = null;
    if (room) {
      try { await room.leave(); } catch (_) { /* The transport may already be closed. */ }
    }
    if (updateStatus) setMultiplayerStatus('Disconnected. Presence only; gameplay synchronization is not active yet.');
  }
  async function connectMultiplayer() {
    if (state.multiplayerConnecting) return;
    if (state.multiplayerRoom) { await disconnectMultiplayer(); return; }
    if (!multiplayerUrl) throw new Error('Multiplayer is not configured for browser access. Set CORE_MULTIPLAYER_PUBLIC_URL in the PHP web-server environment.');
    if (!window.Colyseus?.Client) throw new Error('The Colyseus browser SDK did not load. Check access to the pinned SDK CDN.');
    const systemId = currentSystemId();
    if (!systemId) throw new Error('The character has no current star system and cannot join a universe room.');
    state.multiplayerConnecting = true;
    setMultiplayerStatus('Requesting a short-lived PHP authorization grant…');
    try {
      const grantRequest = { method: 'POST', body: JSON.stringify({ character_id: state.character.id }) };
      let grant;
      try { grant = await request(api('multiplayer/token'), grantRequest); }
      catch (routeError) {
        // Keep a direct PHP fallback for Apache deployments without API rewrites.
        if (!/HTTP 404|HTTP 405|non-JSON data/i.test(routeError.message || '')) throw routeError;
        grant = await request(`${apiRoot}/api/multiplayer-token.php`, grantRequest);
      }
      const identity = grant.identity || {};
      if (identity.characterId !== state.character.id || identity.universeId !== state.universe.id || identity.constellationId !== currentConstellationId() || identity.systemId !== systemId) {
        throw new Error('PHP returned a multiplayer grant for a different character, universe, Constellation, or star system.');
      }
      const client = new window.Colyseus.Client(multiplayerUrl);
      client.auth.token = grant.token;
      const room = await client.joinOrCreate('universe', {
        universeId: state.universe.id,
        systemId,
        locationId: '',
        displayName: state.character.name || 'Explorer'
      });
      state.multiplayerClient = client;
      state.multiplayerRoom = room;
      room.onLeave(() => {
        if (state.multiplayerRoom === room) {
          state.multiplayerRoom = null;
          setMultiplayerStatus('Multiplayer session ended. Reconnect when ready.');
        }
      });
      room.onError((code, message) => setMultiplayerStatus(`Multiplayer server error ${code}: ${message || 'connection error'}`));
      setMultiplayerStatus('Connected to universe session · presence only; gameplay synchronization is not active yet.', true);
    } finally {
      state.multiplayerConnecting = false;
      if (state.multiplayerRoom) setMultiplayerStatus('Connected to universe session · presence only; gameplay synchronization is not active yet.', true);
      else setMultiplayerStatus($('multiplayerStatus')?.textContent || 'Not connected.');
    }
  }
  async function refreshMultiplayerSystemRoom() {
    if (!state.multiplayerRoom) return;
    await disconnectMultiplayer(false);
    await connectMultiplayer();
  }
  $('multiplayerConnectButton')?.addEventListener('click', async () => {
    try { await connectMultiplayer(); }
    catch (error) { setMultiplayerStatus(error.message || 'Could not connect to multiplayer.'); }
  });
  $('multiplayerCopyKeyButton')?.addEventListener('click', async event => {
    const button = event.currentTarget;
    const output = $('multiplayerKeyStatus');
    if (!boot.canShareUniverse || !state.universe?.id) {
      if (output) output.textContent = 'Only the owner of this universe can create a sharing key.';
      return;
    }
    button.disabled = true;
    if (output) output.textContent = 'Generating a new universe sharing key…';
    try {
      const result = await request(api('universe-share.php'), {
        method: 'POST',
        body: JSON.stringify({ universe_id: state.universe.id })
      });
      const key = String(result.share_code || '');
      if (!/^[A-F0-9]{32}$/.test(key)) throw new Error('The sharing service returned an invalid key.');
      try {
        await navigator.clipboard.writeText(key);
        if (output) output.textContent = 'New universe key copied. This rotates the key, so any previous key is now invalid.';
      } catch (_) {
        if (output) output.textContent = `New universe key: ${key} — clipboard access was unavailable. This replaces the previous key.`;
      }
    } catch (error) {
      if (output) output.textContent = error.message || 'Could not create a universe sharing key.';
    } finally {
      button.disabled = false;
    }
  });
  window.addEventListener('pagehide', () => {
    if (state.multiplayerRoom) state.multiplayerRoom.leave();
  });
  if (!multiplayerUrl) setMultiplayerStatus('Not configured for browser access. Set CORE_MULTIPLAYER_PUBLIC_URL on the PHP web server.');
  else setMultiplayerStatus(window.Colyseus?.Client ? 'Ready to connect. Presence only; gameplay synchronization is not active yet.' : (window.CORE_COLYSEUS_SDK_ERROR ? 'Colyseus browser SDK could not load. Check CDN access.' : 'Loading Colyseus browser SDK…'));
  function updateCharacterPanel() {
    $('characterLocation').textContent = state.character ? systemName(currentSystemId()) : 'No character loaded';
    $('characterConstellation').textContent = state.character ? constellationName(currentConstellationId()) : 'No character loaded';
    $('constellationCount').textContent = String(constellations().length);
    $('universeName').textContent = state.universe?.name || `Universe seed: ${state.universe?.seed || state.universe?.baseline?.seed || 'Unavailable'}`;
    $('systemCount').textContent = String(systems().length);
    $('seedFingerprint').textContent = state.universe?.seed_fingerprint || state.universe?.baseline?.seed_fingerprint || '—';
  }
  async function loadNavigation() {
    const result = await request(api(`navigation.php?universe_id=${encodeURIComponent(state.universe.id)}`));
    state.navigation = result.navigation || result;
  }
  function populateTravelDestinations() {
    const select = $('travelDestination');
    if (!select) return;
    const current = currentSystemId();
    select.replaceChildren(new Option('Select a destination star system', ''));
    systems().forEach(system => {
      if (system.id === current) return;
      select.add(new Option(system.name || system.id, system.id));
    });
    const route = state.character?.route_progress;
    const active = route?.status === 'active';
    select.disabled = systems().length < 2 || active;
    $('travelButton').disabled = !active && select.options.length <= 1;
    $('travelButton').textContent = active ? 'Advance one jump' : 'Plan route';
    if (active) $('travelStatus').textContent = `Route to star system ${route.destination_name || systemName(route.destination_system_id)} · ${route.total_hops || 0} jumps planned.`;
  }
  async function previewTravelRoute() {
    const destination = $('travelDestination')?.value || '';
    if (!destination) { $('travelStatus').textContent = 'Select a destination star system first.'; return null; }
    const from = currentSystemId();
    const result = await request(api(`navigation-route.php?universe_id=${encodeURIComponent(state.universe.id)}&from_system_id=${encodeURIComponent(from)}&to_system_id=${encodeURIComponent(destination)}`));
    const path = Array.isArray(result.path) ? result.path : [];
    $('travelStatus').textContent = path.length > 1
      ? `Star system route available · ${path.length - 1} jumps · ${path.map(step => step.name || step.system_id).join(' → ')}`
      : 'You are already at that destination.';
    return result;
  }
  async function executeTravel() {
    const button = $('travelButton');
    const route = state.character?.route_progress;
    button.disabled = true;
    try {
      if (route?.status === 'active') {
        const result = await request(api('character-travel.php'), {
          method: 'POST',
          body: JSON.stringify({
            action: 'advance',
            universe_id: state.universe.id,
            character_id: state.character.id,
            expected_revision: Number(state.character.revision || 0)
          })
        });
        const previousSystemId = currentSystemId();
        state.character = result.character;
        updateCharacterPanel();
        state.selectedConstellationId = currentConstellationId();
        if (currentSystemId() !== previousSystemId && state.multiplayerRoom) {
          try { await refreshMultiplayerSystemRoom(); } catch (error) { setMultiplayerStatus(`Travel completed, but multiplayer room migration failed: ${error.message || error}`); }
        }
        renderMap();
        const progress = state.character.route_progress;
        $('travelStatus').textContent = result.travel_status === 'route_completed'
          ? `Arrived in ${systemName(currentSystemId())}. Star system route complete.`
          : `Jump completed · now in ${systemName(currentSystemId())}.`;
        populateTravelDestinations();
        return;
      }
      const destination = $('travelDestination').value;
      if (!destination) throw new Error('Select a destination star system first.');
      const routePreview = await previewTravelRoute();
      if (!routePreview || !Array.isArray(routePreview.path) || routePreview.path.length < 2) return;
      const result = await request(api('character-travel.php'), {
        method: 'POST',
        body: JSON.stringify({
          action: 'start',
          universe_id: state.universe.id,
          character_id: state.character.id,
          destination_system_id: destination,
          expected_revision: Number(state.character.revision || 0)
        })
      });
      state.character = result.character;
      $('travelStatus').textContent = `Star system route started to ${systemName(destination)} · ${result.character.route_progress.total_hops} jumps. Advance one jump at a time.`;
      populateTravelDestinations();
    } catch (error) {
      $('travelStatus').textContent = error.message || 'Travel request failed.';
    } finally {
      button.disabled = false;
      if (state.character?.route_progress?.status === 'active') button.textContent = 'Advance one jump';
    }
  }
  function selectSystemEntry(entryId) {
    const system=systems().find(s=>s.id===state.selectedSystem);
    const entries=Array.isArray(system?.worlds)?system.worlds:[];
    const world=entries.find((item,index)=>String(item.id||index)===String(entryId));
    if(!world)return;
    state.selectedWorldId=String(world.id||entryId);
    state.selectedWorld=world;
    $('selectedDescription').textContent=`${world.name || 'World'} · ${String(world.type || 'unknown').replaceAll('_',' ')} · UWP ${world.uwp || '—'} · Open the System View to inspect its orbital layout.`;
    const list=$('selectedWorldList');
    list?.querySelectorAll('[data-world-id]').forEach(item=>{
      const active=item.dataset.worldId===state.selectedWorldId;
      item.classList.toggle('is-selected',active);
      item.setAttribute('aria-pressed',active?'true':'false');
    });
    window.dispatchEvent(new CustomEvent('core:world-selected',{detail:{world}}));
    if (window.CORE_SYSTEM_VIEW?.activeSystemId === state.selectedSystem) window.CORE_SYSTEM_VIEW.select?.(world);
  }
  function renderConstellationSystemList() {
    const list = $('constellationSystemList');
    if (!list) return;
    list.replaceChildren();
    visibleSystems().forEach(system => {
      const item = document.createElement('li');
      item.dataset.systemId = system.id;
      item.tabIndex = 0;
      item.setAttribute('role', 'button');
      item.setAttribute('aria-pressed', system.id === state.selectedSystem ? 'true' : 'false');
      if (system.id === state.selectedSystem) item.classList.add('is-selected');
      const title = document.createElement('strong');
      title.textContent = system.name || system.id;
      const detail = document.createElement('span');
      detail.textContent = `${system.classification || 'Unclassified'} · ${Array.isArray(system.worlds) ? system.worlds.length : 0} worlds`;
      item.append(title, detail);
      list.append(item);
    });
  }
  function selectSystem(id) {
    const system=systems().find(s=>s.id===id); if(!system)return;
    if (system.constellation_id && system.constellation_id !== state.selectedConstellationId) state.selectedConstellationId = system.constellation_id;
    state.selectedSystem=id;
    state.selectedWorld=null;
    state.selectedWorldId=null;
    if ($('enterWorldButton')) $('enterWorldButton').disabled=true;
    if ($('worldEntryStatus')) $('worldEntryStatus').textContent='Select a world in the character current star system to enter the test environment.';
    $('selectedName').textContent=system.name||id;
    $('selectedDescription').textContent=`${system.classification||'Unclassified'} star system in ${constellationName(system.constellation_id)}. Select a world below or open the system view.`;
    $('selectedId').textContent=id;
    $('selectedClassification').textContent=system.classification||'Unclassified';
    $('selectedCoordinates').textContent=`${system.x}, ${system.y}`;
    const worlds=Array.isArray(system.worlds)?system.worlds:[];
    $('selectedWorlds').textContent=String(worlds.length);
    const worldList=$('selectedWorldList');
    if(worldList){
      worldList.replaceChildren();
      state.selectedWorldId=null;
      if(!worlds.length){
        const empty=document.createElement('li');empty.textContent='No worlds are currently generated in this system.';worldList.append(empty);
        $('selectedName').textContent=system.name||id;
        $('selectedDescription').textContent=`${system.classification||'Unclassified'} star system. No worlds are currently generated.`;
      } else {
        worlds.forEach((entry,index)=>{
          const item=document.createElement('li');
          item.dataset.worldId=String(entry.id||index);
          item.tabIndex=0;
          item.setAttribute('role','button');
          item.setAttribute('aria-pressed','false');
          const title=document.createElement('strong');title.textContent=entry.name||entry.id||'Unnamed world';
          const detail=document.createElement('span');detail.textContent=`${String(entry.type||'unknown').replaceAll('_',' ')} · UWP ${entry.uwp||'—'} · TL ${entry.tech_level??'—'}`;
          item.append(title,detail);worldList.append(item);
        });
        selectSystemEntry(worlds[0].id||'0');
      }
    }
    renderConstellationSystemList();
    updateConstellationViewHeading();
    state.nodes.forEach((mesh,key)=>{if(mesh.material){mesh.material.emissiveColor=key===id?new BABYLON.Color3(.35,.22,.03):new BABYLON.Color3(.04,.18,.24);}});
    const viewButton=$('viewSystemButton'); if(viewButton){viewButton.disabled=false;viewButton.hidden=state.view==='system';viewButton.textContent='View Selected System';}
    updateWorldEntryControl();
  }
  $('constellationSystemList')?.addEventListener('click',event=>{
    const item=event.target.closest('[data-constellation-id],[data-system-id]');
    if(!item)return;
    if(item.dataset.constellationId)selectConstellation(item.dataset.constellationId);else if(item.dataset.systemId)selectSystem(item.dataset.systemId);
  });
  $('constellationSystemList')?.addEventListener('keydown',event=>{
    if(event.key!=='Enter'&&event.key!==' ')return;
    const item=event.target.closest('[data-constellation-id],[data-system-id]');
    if(item){event.preventDefault();if(item.dataset.constellationId)selectConstellation(item.dataset.constellationId);else if(item.dataset.systemId)selectSystem(item.dataset.systemId);}
  });
  $('selectedWorldList')?.addEventListener('click',event=>{
    const item=event.target.closest('[data-world-id]');
    if(item)selectSystemEntry(item.dataset.worldId);
  });
  $('selectedWorldList')?.addEventListener('keydown',event=>{
    if(event.key!=='Enter'&&event.key!==' ')return;
    const item=event.target.closest('[data-world-id]');
    if(item){event.preventDefault();selectSystemEntry(item.dataset.worldId);}
  });
  function renderConstellationMap() {
    setEntityListMode('constellation');
    $('universeViewToolbar')?.classList.add('hidden');
    $('constellationViewToolbar')?.classList.remove('hidden');
    const list=visibleSystems();
    updateConstellationViewHeading();
    if(!list.length){$('canvasFallback').classList.remove('hidden');$('canvasFallback').textContent='This Constellation contains no generated star systems.';setStatus('No star systems are available in this Constellation.');return;}
    if(!window.BABYLON){$('canvasFallback').classList.remove('hidden');$('canvasFallback').textContent='Babylon.js failed to load. Check CDN/network access.';setStatus('3D engine unavailable.');return;}
    const canvas=$('renderCanvas');
    try {
      if(!state.engine){
        state.engine=new BABYLON.Engine(canvas,true,{preserveDrawingBuffer:true,stencil:true,adaptToDeviceRatio:true});
        state.scene=new BABYLON.Scene(state.engine); window.CORE_DIAGNOSTICS?.captureScene(state.scene,'universe-constellation'); state.scene.clearColor=new BABYLON.Color4(.025,.045,.075,1);
        const camera=new BABYLON.ArcRotateCamera('map-camera',-Math.PI/2,Math.PI/3,25,BABYLON.Vector3.Zero(),state.scene);
        camera.lowerRadiusLimit=3;camera.upperRadiusLimit=150;camera.wheelDeltaPercentage=.015;camera.panningSensibility=45;camera.useCtrlForPanning=false;camera.panningMouseButton=0;camera.attachControl(canvas,true);
        new BABYLON.HemisphericLight('ambient',new BABYLON.Vector3(0,1,0),state.scene).intensity=1;
        state.engine.runRenderLoop(()=>{if(state.scene&&!state.scene.isDisposed)state.scene.render();});
        window.addEventListener('resize',()=>state.engine?.resize());
        if('ResizeObserver'in window){
          state.resizeObserver=new ResizeObserver(entries=>{
            const rect=entries[0]?.contentRect;if(!rect||!state.engine)return;
            const width=Math.round(rect.width),height=Math.round(rect.height);
            if(width<1||height<1)return;
            if(state.lastCanvasSize?.width===width&&state.lastCanvasSize?.height===height)return;
            state.lastCanvasSize={width,height};
            requestAnimationFrame(()=>{if(state.engine&&!state.scene?.isDisposed)state.engine.resize();});
          });
          state.resizeObserver.observe(canvas.parentElement);
        }
        canvas.addEventListener('pointerdown',e=>{state.dragStart={x:e.clientX,y:e.clientY};state.dragged=false;},{passive:true});
        canvas.addEventListener('pointermove',e=>{if(state.dragStart&&Math.hypot(e.clientX-state.dragStart.x,e.clientY-state.dragStart.y)>6)state.dragged=true;},{passive:true});
        canvas.addEventListener('pointerup',()=>{setTimeout(()=>{state.dragStart=null;state.dragged=false;},0);},{passive:true});
        state.scene.onPointerObservable.add(info=>{
          if(info.type!==BABYLON.PointerEventTypes.POINTERPICK||!info.pickInfo?.hit||state.dragged)return;
          const mesh=info.pickInfo.pickedMesh;const id=mesh?.metadata?.entityId;if(!id)return;if(mesh.metadata.entityType==='constellation')selectConstellation(id);else selectSystem(id);
        });
      }
      $('canvasFallback').classList.add('hidden');state.engine.resize();state.nodes.forEach(m=>m.dispose(false,true));state.nodes.clear();
      state.scene.meshes.filter(m=>m.name.startsWith('route-') || m.name.startsWith('universe-route-') || m.name.startsWith('constellation-label-')).forEach(m=>m.dispose(false,true));
      const finite=v=>Number.isFinite(Number(v))?Number(v):0;
      const xs=list.map(s=>finite(s.x)),ys=list.map(s=>finite(s.y));const minX=Math.min(...xs),maxX=Math.max(...xs),minY=Math.min(...ys),maxY=Math.max(...ys);const span=Math.max(maxX-minX,maxY-minY,1),scale=38/span;
      const coords=new Map(list.map(s=>[s.id,new BABYLON.Vector3((finite(s.x)-(minX+maxX)/2)*scale,0,(finite(s.y)-(minY+maxY)/2)*scale)]));
      const visibleIds=new Set(list.map(system=>system.id));
      const routes=state.navigation?.routes||state.navigation?.edges||[];routes.filter(route=>visibleIds.has(route.from)&&visibleIds.has(route.to)).forEach((r,i)=>{const a=coords.get(r.from),b=coords.get(r.to);if(!a||!b)return;const line=BABYLON.MeshBuilder.CreateLines(`route-${i}`,{points:[a,b]},state.scene);line.color=new BABYLON.Color3(.18,.38,.49);line.isPickable=false;});
      list.forEach((system,i)=>{
        const position=coords.get(system.id);
        const mesh=BABYLON.MeshBuilder.CreateSphere(`system-${i}`,{diameter:1.45,segments:16},state.scene);
        mesh.position.copyFrom(position);mesh.metadata={entityId:system.id,entityType:'system'};
        const mat=new BABYLON.StandardMaterial(`system-material-${i}`,state.scene);
        mat.diffuseColor=new BABYLON.Color3(.18,.68,.82);mat.emissiveColor=new BABYLON.Color3(.10,.42,.56);mesh.material=mat;state.nodes.set(system.id,mesh);
        const angle=(i/Math.max(1,list.length))*Math.PI*2;
        const labelPosition=position.add(new BABYLON.Vector3(Math.cos(angle)*2.5,2.1,Math.sin(angle)*2.5));
        createMapLabel(system.name||system.id,`${system.worlds?.length||0} worlds`,labelPosition,5.4,1.35,state.scene,`constellation-label-system-${i}`);
      });
      const camera=state.scene.activeCamera;const focusId=visibleIds.has(currentSystemId())?currentSystemId():(visibleIds.has(state.selectedSystem)?state.selectedSystem:list[0].id);camera.setTarget(coords.get(focusId)||BABYLON.Vector3.Zero());camera.radius=Math.max(22,Math.min(78,span*1.65));camera.alpha=-Math.PI/2;camera.beta=Math.PI/3;state.engine.resize();
      const visibleRouteCount=routes.filter(route=>visibleIds.has(route.from)&&visibleIds.has(route.to)).length;
      setStatus(`${list.length} star systems in ${constellationName(state.selectedConstellationId)} · ${visibleRouteCount} navigation links · drag to pan · wheel to zoom`);
      selectSystem(visibleIds.has(state.selectedSystem)?state.selectedSystem:focusId);
    } catch(error){$('canvasFallback').classList.remove('hidden');$('canvasFallback').textContent=`3D map initialization failed: ${error.message||error}`;setStatus('Map initialization failed.');}
  }
  function renderUniverseMap() {
    state.view = 'universe';
    $('universeViewToolbar')?.classList.remove('hidden');
    $('constellationViewToolbar')?.classList.add('hidden');
    $('systemViewPanel')?.classList.add('hidden');
    $('renderCanvas')?.parentElement?.classList.remove('hidden');
    $('selectedPanel')?.classList.remove('hidden');
    $('systemWorldInspector')?.classList.add('hidden');
    $('viewSystemButton')?.removeAttribute('hidden');
    setEntityListMode('constellation');
    if (!window.BABYLON) { $('canvasFallback').classList.remove('hidden'); $('canvasFallback').textContent = 'Babylon.js failed to load. Check CDN/network access.'; setStatus('3D engine unavailable.'); return; }
    const list = constellations();
    if (!list.length) { $('canvasFallback').classList.remove('hidden'); $('canvasFallback').textContent = 'No constellations are available in this Universe.'; setStatus('No constellations are available.'); return; }
    const canvas = $('renderCanvas');
    try {
      if (!state.engine) {
        state.engine = new BABYLON.Engine(canvas, true, { preserveDrawingBuffer: true, stencil: true, adaptToDeviceRatio: true });
        state.scene = new BABYLON.Scene(state.engine);
        window.CORE_DIAGNOSTICS?.captureScene(state.scene, 'universe-constellation');
        state.scene.clearColor = new BABYLON.Color4(.018, .032, .055, 1);
        const camera = new BABYLON.ArcRotateCamera('map-camera', -Math.PI / 2, Math.PI / 3, 42, BABYLON.Vector3.Zero(), state.scene);
        camera.lowerRadiusLimit = 8; camera.upperRadiusLimit = 180; camera.wheelDeltaPercentage = .012; camera.panningSensibility = 45; camera.useCtrlForPanning = false; camera.panningMouseButton = 0; camera.attachControl(canvas, true);
        new BABYLON.HemisphericLight('ambient', new BABYLON.Vector3(0, 1, 0), state.scene).intensity = 1;
        state.engine.runRenderLoop(() => { if (state.scene && !state.scene.isDisposed) state.scene.render(); });
        window.addEventListener('resize', () => state.engine?.resize());
        if ('ResizeObserver' in window) {
          state.resizeObserver = new ResizeObserver(entries => { const rect = entries[0]?.contentRect; if (!rect || !state.engine) return; const width = Math.round(rect.width), height = Math.round(rect.height); if (width < 1 || height < 1 || (state.lastCanvasSize?.width === width && state.lastCanvasSize?.height === height)) return; state.lastCanvasSize = { width, height }; requestAnimationFrame(() => { if (state.engine && !state.scene?.isDisposed) state.engine.resize(); }); });
          state.resizeObserver.observe(canvas.parentElement);
        }
        canvas.addEventListener('pointerdown', e => { state.dragStart = { x: e.clientX, y: e.clientY }; state.dragged = false; }, { passive: true });
        canvas.addEventListener('pointermove', e => { if (state.dragStart && Math.hypot(e.clientX - state.dragStart.x, e.clientY - state.dragStart.y) > 6) state.dragged = true; }, { passive: true });
        canvas.addEventListener('pointerup', () => { setTimeout(() => { state.dragStart = null; state.dragged = false; }, 0); }, { passive: true });
        state.scene.onPointerObservable.add(info => { if (info.type !== BABYLON.PointerEventTypes.POINTERPICK || !info.pickInfo?.hit || state.dragged) return; const mesh = info.pickInfo.pickedMesh; const id = mesh?.metadata?.entityId; if (id && mesh.metadata.entityType === 'constellation') selectConstellation(id); });
      }
      $('canvasFallback').classList.add('hidden'); state.engine.resize();
      state.nodes.forEach(mesh => mesh.dispose(false, true)); state.nodes.clear();
      state.scene.meshes.filter(mesh => mesh.name.startsWith('route-') || mesh.name.startsWith('universe-route-') || mesh.name.startsWith('constellation-label-')).forEach(mesh => mesh.dispose(false, true));
      const finite = value => Number.isFinite(Number(value)) ? Number(value) : 0;
      const coordsRaw = list.map(item => {
        const related = systems().filter(system => system.constellation_id === item.id);
        const x = Number.isFinite(Number(item.x)) ? Number(item.x) : (related.length ? related.reduce((sum, system) => sum + finite(system.x), 0) / related.length : 0);
        const y = Number.isFinite(Number(item.y)) ? Number(item.y) : (related.length ? related.reduce((sum, system) => sum + finite(system.y), 0) / related.length : 0);
        return { item, x, y };
      });
      const xs = coordsRaw.map(item => item.x), ys = coordsRaw.map(item => item.y);
      const span = Math.max(Math.max(...xs) - Math.min(...xs), Math.max(...ys) - Math.min(...ys), 1);
      const scale = 48 / span;
      const centerX = (Math.min(...xs) + Math.max(...xs)) / 2, centerY = (Math.min(...ys) + Math.max(...ys)) / 2;
      const coords = new Map(coordsRaw.map(({ item, x, y }) => [item.id, new BABYLON.Vector3((x - centerX) * scale, 0, (y - centerY) * scale)]));
      const routes = state.navigation?.routes || state.navigation?.edges || [];
      const constellationLinks = new Set();
      routes.forEach(route => {
        const from = systems().find(system => system.id === route.from), to = systems().find(system => system.id === route.to);
        if (!from || !to || from.constellation_id === to.constellation_id) return;
        const pair = [from.constellation_id, to.constellation_id].sort();
        constellationLinks.add(pair.join('|'));
      });
      [...constellationLinks].forEach((pair, index) => { const [from, to] = pair.split('|'); const a = coords.get(from), b = coords.get(to); if (!a || !b) return; const line = BABYLON.MeshBuilder.CreateDashedLines(`universe-route-${index}`, { points: [a, b], dashSize: 1.1, gapSize: .8, dashNb: 18 }, state.scene); line.color = new BABYLON.Color3(.18, .48, .62); line.alpha = .72; line.isPickable = false; });
      coordsRaw.forEach(({ item }, index) => {
        const position = coords.get(item.id); const count = systems().filter(system => system.constellation_id === item.id).length;
        const marker = BABYLON.MeshBuilder.CreateSphere(`constellation-marker-${index}`, { diameter: 2.4 + Math.min(1.1, Math.sqrt(count) * .22), segments: 20 }, state.scene);
        marker.position.copyFrom(position); marker.metadata = { entityId: item.id, entityType: 'constellation' };
        const material = new BABYLON.StandardMaterial(`constellation-marker-material-${index}`, state.scene); material.diffuseColor = new BABYLON.Color3(.12, .55, .72); material.emissiveColor = item.id === state.selectedConstellationId ? new BABYLON.Color3(.42, .31, .08) : new BABYLON.Color3(.06, .27, .39); material.specularColor = BABYLON.Color3.Black(); marker.material = material; state.nodes.set(item.id, marker);
        const angle = (index / Math.max(1, coordsRaw.length)) * Math.PI * 2;
        const labelPosition = position.add(new BABYLON.Vector3(Math.cos(angle) * 3.2, 3.0, Math.sin(angle) * 3.2));
        createMapLabel(item.name || item.id, `${count} star systems`, labelPosition, 7.2, 1.8, state.scene, `constellation-label-${index}`);
      });
      const camera = state.scene.activeCamera; camera.setTarget(BABYLON.Vector3.Zero()); camera.radius = Math.max(34, Math.min(92, span * scale * 1.42)); camera.alpha = -Math.PI / 2; camera.beta = Math.PI / 3; state.engine.resize();
      selectConstellation(constellations().some(item => item.id === state.selectedConstellationId) ? state.selectedConstellationId : list[0].id);
      setStatus(`${list.length} constellations · ${systems().length} star systems · select a constellation to zoom in · drag to rotate/pan · wheel to zoom`);
    } catch (error) { $('canvasFallback').classList.remove('hidden'); $('canvasFallback').textContent = `Universe map initialization failed: ${error.message || error}`; setStatus('Universe map initialization failed.'); }
  }
  function renderMap() { if (state.view === 'universe') renderUniverseMap(); else if (state.view === 'constellation') renderConstellationMap(); }

  function setEntityListMode(mode) {
    const systemsList = $('constellationSystemList');
    const worldsList = $('selectedWorldList');
    const heading = $('entityListHeading');
    const showingWorlds = mode === 'system';
    systemsList?.classList.toggle('hidden', showingWorlds);
    worldsList?.classList.toggle('hidden', !showingWorlds);
    if (heading) heading.textContent = showingWorlds ? 'WORLDS IN THIS SYSTEM' : (state.view === 'universe' ? 'CONSTELLATIONS IN THIS UNIVERSE' : 'STAR SYSTEMS IN THIS CONSTELLATION');
  }
  function drawSystemView(systemId) {
    const system=systems().find(item=>item.id===systemId);
    if(!system) return;
    if(typeof window.CORE_SYSTEM_VIEW?.open !== 'function') {
      $('systemViewFallback').classList.remove('hidden');
      $('systemViewFallback').textContent='The star system detail viewer could not be loaded. Refresh the page and check system-view.js.';
      return;
    }
    state.selectedSystem = normalizeEntityId(system.id);
    const selectedWorld = state.selectedWorld;
    const universeCamera = state.scene?.activeCamera;
    state.universeCamera = universeCamera ? { alpha: universeCamera.alpha, beta: universeCamera.beta, radius: universeCamera.radius, target: universeCamera.target.clone() } : null;
    state.view = 'system';
    $('selectedPanel')?.classList.add('hidden');
    $('systemWorldInspector')?.classList.remove('hidden');
    const viewButton=$('viewSystemButton'); if(viewButton) viewButton.hidden=true;
    setEntityListMode('system');
    state.selectedConstellationId = system.constellation_id || state.selectedConstellationId;
    updateConstellationViewHeading();
    window.CORE_SYSTEM_VIEW.open(system);
    if (selectedWorld && Array.isArray(system.worlds) && system.worlds.some(world => world.id === selectedWorld.id)) {
      window.CORE_SYSTEM_VIEW.select?.(selectedWorld);
    }
    $('systemViewTitle').textContent='System View';
    $('systemViewName').textContent=system.name||system.id;
    $('systemViewPanel').classList.remove('hidden');
    $('constellationViewToolbar')?.classList.add('hidden');
    $('renderCanvas').parentElement.classList.add('hidden');
  }
  $('viewSystemButton')?.addEventListener('click',()=>{if(state.view==='universe'){if(!state.selectedConstellationId)return;state.view='constellation';setEntityListMode('constellation');renderMap();return;}if(state.view==='constellation'&&state.selectedSystem)drawSystemView(state.selectedSystem);});
  window.addEventListener('core:world-selected', event => {
    state.selectedWorld = event.detail?.world || null;
    state.selectedWorldId = state.selectedWorld ? normalizeEntityId(state.selectedWorld.id) : null;
    $('selectedWorldList')?.querySelectorAll('[data-world-id]').forEach(item => {
      const active = item.dataset.worldId === state.selectedWorldId;
      item.classList.toggle('is-selected', active);
      item.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    updateWorldEntryControl();
  });
  $('enterWorldButton')?.addEventListener('click', () => {
    const context = selectedWorldEntryContext();
    if (!context.ok || !state.character?.id) {
      updateWorldEntryControl();
      return;
    }
    const target = new URL('world.php', window.location.href);
    target.searchParams.set('character_id', state.character.id);
    target.searchParams.set('system_id', context.system.id);
    target.searchParams.set('world_id', context.world.id);
    window.location.href = target.toString();
  });
  $('travelDestination')?.addEventListener('change', async () => {
    if (state.character?.route_progress?.status === 'active') return;
    try { await previewTravelRoute(); }
    catch (error) { $('travelStatus').textContent = error.message || 'Route preview failed.'; }
  });
  $('travelButton')?.addEventListener('click', executeTravel);
  $('backToUniverseButton')?.addEventListener('click',()=>{
    const system=systems().find(item=>item.id===state.selectedSystem);
    if(system?.constellation_id) state.selectedConstellationId=system.constellation_id;
    state.view='constellation';
    const viewButton=$('viewSystemButton'); if(viewButton) viewButton.hidden=false;
    setEntityListMode('constellation');
    $('systemViewPanel').classList.add('hidden');
    $('selectedPanel')?.classList.remove('hidden');
    $('systemWorldInspector')?.classList.add('hidden');
    $('viewSystemButton')?.removeAttribute('hidden');
    $('constellationViewToolbar')?.classList.remove('hidden');
    $('renderCanvas').parentElement.classList.remove('hidden');
    window.CORE_SYSTEM_VIEW?.close();
    updateConstellationViewHeading();
    renderMap();
    if (state.universeCamera && state.scene?.activeCamera) {
      const camera = state.scene.activeCamera;
      camera.alpha = state.universeCamera.alpha;
      camera.beta = state.universeCamera.beta;
      camera.radius = state.universeCamera.radius;
      camera.setTarget(state.universeCamera.target);
    }
    state.universeCamera = null;
  });
  $('constellationSelect')?.addEventListener('change',event=>{
    const nextId=String(event.target.value||'');
    if(!constellations().some(item=>item.id===nextId)) return;
    state.selectedConstellationId=nextId;
    state.selectedSystem=null;
    state.selectedWorld=null;
    state.selectedWorldId=null;
    state.view='constellation';
    const viewButton=$('viewSystemButton'); if(viewButton) viewButton.hidden=false;
    setEntityListMode('constellation');
    state.universeCamera=null;
    $('systemViewPanel').classList.add('hidden');
    $('selectedPanel')?.classList.remove('hidden');
    $('systemWorldInspector')?.classList.add('hidden');
    $('viewSystemButton')?.removeAttribute('hidden');
    $('constellationViewToolbar')?.classList.remove('hidden');
    $('renderCanvas').parentElement.classList.remove('hidden');
    window.CORE_SYSTEM_VIEW?.close();
    renderMap();
  });
  async function start() {
    if(!state.universe||!state.character){throw new Error('The selected character or assigned universe was not embedded in the gameplay page. Return to Character Selection and choose Play again.');}
    if(!state.universe.id||!Array.isArray(state.universe.baseline?.systems))throw new Error('The assigned universe record is missing its ID or generated star-system data.');
    const activeUniverseId = String(state.character.active_universe_id || state.character.universe_id || '');
    if(activeUniverseId!==state.universe.id)throw new Error('The selected character is not currently in the universe loaded by this page.');
    if(state.character.lifecycle?.enabled===false)throw new Error('This character is disabled. Enable it in the Workbench before playing.');
    if(state.character.lifecycle?.locked===true)throw new Error('This character is locked. Unlock it in the Workbench before playing.');
    $('gameLayout').classList.remove('hidden');
    state.selectedConstellationId = currentConstellationId() || constellations()[0]?.id || '';
    state.view = 'universe';
    setEntityListMode('constellation');
    updateCharacterPanel();
    if (multiplayerUrl) setMultiplayerStatus(window.Colyseus?.Client ? 'Ready to connect. Presence only; gameplay synchronization is not active yet.' : 'Colyseus browser SDK unavailable.');
    let navigationWarning = '';
    try { await loadNavigation(); } catch (error) { state.navigation = null; navigationWarning = ` Navigation links could not be loaded: ${error.message}`; }
    selectConstellation(state.selectedConstellationId || constellations()[0]?.id);
    renderMap();
    populateTravelDestinations();
    notice(`Loaded ${state.character.name} into ${state.universe.name||'the assigned universe'} · ${constellationName(state.selectedConstellationId)}.${navigationWarning}`, navigationWarning ? 'error' : 'success');
  }
  start().catch(error=>{notice(`Could not enter gameplay: ${error.message}`,'error');setStatus('Gameplay initialization failed.');});
})();
