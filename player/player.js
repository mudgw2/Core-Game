(() => {
  'use strict';
  window.CORE_PLAYER_SCRIPT_LOADED = true;
  const $ = id => document.getElementById(id);
  // Catch synchronous startup faults that occur before start().catch() is reached.
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
  const state = { universe: boot.universe || null, character: boot.character || null, engine: null, scene: null, nodes: new Map(), navigation: null, selectedSystem: null, dragStart: null, dragged: false };
  const notice = (message, type='') => { const el=$('notice'); if(el){el.textContent=message;el.className=`notice ${type}`.trim();} };
  async function request(url, options={}) {
    const response = await fetch(url, {signal: AbortSignal.timeout(12000), headers:{Accept:'application/json',...(options.body?{'Content-Type':'application/json'}:{})},...options});
    const raw = await response.text(); let data;
    try { data = JSON.parse(raw); } catch { throw new Error(`API ${url} returned non-JSON data (HTTP ${response.status}). ${raw.slice(0,180)}`); }
    if (!response.ok || data.ok === false) throw new Error(data.error?.message || `Request failed (HTTP ${response.status}).`);
    return data;
  }
  const systems = () => state.universe?.baseline?.systems || [];
  const systemName = id => systems().find(s=>s.id===id)?.name || id || 'Unknown';
  const currentSystemId = () => state.character?.current_system_id || state.character?.location?.system_id || '';
  function setStatus(message) { $('mapStatus').textContent=message; }
  function updateCharacterPanel() {
    $('characterLocation').textContent = state.character ? systemName(currentSystemId()) : 'No character loaded';
    $('universeName').textContent = state.universe?.name || `Universe seed: ${state.universe?.seed || state.universe?.baseline?.seed || 'Unavailable'}`;
    $('systemCount').textContent = String(systems().length);
    $('seedFingerprint').textContent = state.universe?.seed_fingerprint || state.universe?.baseline?.seed_fingerprint || '—';
  }
  async function loadNavigation() {
    const result = await request(api(`navigation.php?universe_id=${encodeURIComponent(state.universe.id)}`));
    state.navigation = result.navigation || result;
  }
  function selectSystem(id) {
    const system=systems().find(s=>s.id===id); if(!system)return;
    state.selectedSystem=id;
    $('selectedName').textContent=system.name||id;
    $('selectedDescription').textContent=`${system.classification||'Unclassified'} system. Select another marker to inspect it; drag the map to pan.`;
    $('selectedId').textContent=id;
    $('selectedClassification').textContent=system.classification||'Unclassified';
    $('selectedCoordinates').textContent=`${system.x}, ${system.y}`;
    $('selectedWorlds').textContent=Array.isArray(system.worlds)?String(system.worlds.length):'Not generated yet';
    state.nodes.forEach((mesh,key)=>{if(mesh.material){mesh.material.emissiveColor=key===id?new BABYLON.Color3(.35,.22,.03):new BABYLON.Color3(.04,.18,.24);}});
    const button=$('travelButton'); const canTravel=Boolean(state.character && id!==currentSystemId()); button.disabled=!canTravel;
    $('travelHint').textContent=!state.character?'No character is loaded.':id===currentSystemId()?'This is your current system.':`Destination: ${system.name||id}. Travel uses the saved navigation graph.`;
  }
  function renderMap() {
    const list=systems(); if(!list.length){$('canvasFallback').classList.remove('hidden');$('canvasFallback').textContent='The assigned universe contains no generated systems.';setStatus('Universe data has no systems.');return;}
    if(!window.BABYLON){$('canvasFallback').classList.remove('hidden');$('canvasFallback').textContent='Babylon.js failed to load. Check CDN/network access.';setStatus('3D engine unavailable.');return;}
    const canvas=$('renderCanvas');
    try {
      if(!state.engine){
        state.engine=new BABYLON.Engine(canvas,true,{preserveDrawingBuffer:true,stencil:true,adaptToDeviceRatio:true});
        state.scene=new BABYLON.Scene(state.engine); state.scene.clearColor=new BABYLON.Color4(.025,.045,.075,1);
        const camera=new BABYLON.ArcRotateCamera('map-camera',-Math.PI/2,Math.PI/3,25,BABYLON.Vector3.Zero(),state.scene);
        camera.lowerRadiusLimit=3;camera.upperRadiusLimit=150;camera.wheelDeltaPercentage=.015;camera.panningSensibility=45;camera.useCtrlForPanning=false;camera.panningMouseButton=0;camera.attachControl(canvas,true);
        new BABYLON.HemisphericLight('ambient',new BABYLON.Vector3(0,1,0),state.scene).intensity=1;
        state.engine.runRenderLoop(()=>{if(state.scene&&!state.scene.isDisposed)state.scene.render();});
        window.addEventListener('resize',()=>state.engine?.resize());
        if('ResizeObserver'in window)new ResizeObserver(()=>state.engine?.resize()).observe(canvas.parentElement);
        // Prevent a drag gesture from selecting a star on pointer-up; ordinary clicks still select.
        canvas.addEventListener('pointerdown',e=>{state.dragStart={x:e.clientX,y:e.clientY};state.dragged=false;},{passive:true});
        canvas.addEventListener('pointermove',e=>{if(state.dragStart&&Math.hypot(e.clientX-state.dragStart.x,e.clientY-state.dragStart.y)>6)state.dragged=true;},{passive:true});
        canvas.addEventListener('pointerup',()=>{setTimeout(()=>{state.dragStart=null;state.dragged=false;},0);},{passive:true});
        state.scene.onPointerObservable.add(info=>{
          if(info.type!==BABYLON.PointerEventTypes.POINTERPICK||!info.pickInfo?.hit||state.dragged)return;
          const mesh=info.pickInfo.pickedMesh;const id=mesh?.metadata?.entityId;if(id)selectSystem(id);
        });
      }
      $('canvasFallback').classList.add('hidden');state.engine.resize();state.nodes.forEach(m=>m.dispose());state.nodes.clear();
      state.scene.meshes.filter(m=>m.name.startsWith('route-')||m.name==='map-grid').forEach(m=>m.dispose());
      const finite=v=>Number.isFinite(Number(v))?Number(v):0;
      const xs=list.map(s=>finite(s.x)),ys=list.map(s=>finite(s.y));const minX=Math.min(...xs),maxX=Math.max(...xs),minY=Math.min(...ys),maxY=Math.max(...ys);const span=Math.max(maxX-minX,maxY-minY,1),scale=24/span;
      const coords=new Map(list.map(s=>[s.id,new BABYLON.Vector3((finite(s.x)-(minX+maxX)/2)*scale,0,(finite(s.y)-(minY+maxY)/2)*scale)]));
      const grid=BABYLON.MeshBuilder.CreateGround('map-grid',{width:32,height:32,subdivisions:16},state.scene);const gm=new BABYLON.StandardMaterial('map-grid-material',state.scene);gm.wireframe=true;gm.emissiveColor=new BABYLON.Color3(.06,.12,.17);gm.alpha=.35;grid.material=gm;grid.isPickable=false;grid.position.y=-.18;
      const routes=state.navigation?.routes||state.navigation?.edges||[];routes.forEach((r,i)=>{const a=coords.get(r.from),b=coords.get(r.to);if(!a||!b)return;const line=BABYLON.MeshBuilder.CreateLines(`route-${i}`,{points:[a,b]},state.scene);line.color=new BABYLON.Color3(.18,.38,.49);line.isPickable=false;});
      list.forEach((system,i)=>{const mesh=BABYLON.MeshBuilder.CreateSphere(`system-${i}`,{diameter:.8,segments:12},state.scene);mesh.position.copyFrom(coords.get(system.id));mesh.metadata={entityId:system.id};const mat=new BABYLON.StandardMaterial(`system-material-${i}`,state.scene);mat.diffuseColor=new BABYLON.Color3(.18,.68,.82);mat.emissiveColor=new BABYLON.Color3(.04,.18,.24);mesh.material=mat;state.nodes.set(system.id,mesh);});
      const camera=state.scene.activeCamera;camera.setTarget(coords.get(currentSystemId())||BABYLON.Vector3.Zero());camera.radius=Math.max(18,Math.min(70,span*1.35));camera.alpha=-Math.PI/2;camera.beta=Math.PI/3;state.engine.resize();
      setStatus(`${list.length} systems rendered · ${routes.length} navigation links · drag to pan · wheel to zoom`);selectSystem(currentSystemId()||list[0].id);
    } catch(error){$('canvasFallback').classList.remove('hidden');$('canvasFallback').textContent=`3D map initialization failed: ${error.message||error}`;setStatus('Map initialization failed.');}
  }
  const travelButton = $('travelButton');
  if (!travelButton) { notice('Gameplay page is incomplete: travelButton is missing. Check that game.php and player.js are from the same CORE release.', 'error'); return; }
  travelButton.addEventListener('click',async()=>{
    if(!state.character||!state.selectedSystem||!state.universe)return;
    const button=$('travelButton');button.disabled=true;
    try{
      const route=await request(api(`navigation-route.php?universe_id=${encodeURIComponent(state.universe.id)}&from_system_id=${encodeURIComponent(currentSystemId())}&to_system_id=${encodeURIComponent(state.selectedSystem)}`));
      let character=state.character;const started=await request(api('character-travel.php'),{method:'POST',body:JSON.stringify({action:'start',universe_id:state.universe.id,character_id:character.id,destination_system_id:state.selectedSystem,expected_revision:Number(character.revision??0)})});character=started.character||character;
      for(let i=0;i<Number(route.hops||0);i++){notice(`Travel in progress · jump ${i+1} of ${route.hops}…`);const advanced=await request(api('character-travel.php'),{method:'POST',body:JSON.stringify({action:'advance',universe_id:state.universe.id,character_id:character.id,expected_revision:Number(character.revision??0)})});character=advanced.character||character;}
      const fresh=await request(api(`character-record.php?character_id=${encodeURIComponent(character.id)}`));state.character=fresh.character||character;updateCharacterPanel();renderMap();notice(`Arrived at ${systemName(currentSystemId())}. ${route.hops||0} jump(s) completed.`,'success');
    }catch(error){notice(`Travel failed: ${error.message}`,'error');}finally{if(state.selectedSystem)selectSystem(state.selectedSystem);}
  });
  async function start() {
    if(!state.universe||!state.character){throw new Error('The selected character or assigned universe was not embedded in the gameplay page. Return to Character Selection and choose Play again.');}
    if(!state.universe.id||!Array.isArray(state.universe.baseline?.systems))throw new Error('The assigned universe record is missing its ID or generated system data.');
    if(state.character.universe_id!==state.universe.id)throw new Error('The selected character is not assigned to the universe loaded by this page.');
    if(state.character.lifecycle?.enabled===false)throw new Error('This character is disabled. Enable it in the Workbench before playing.');
    if(state.character.lifecycle?.locked===true)throw new Error('This character is locked. Unlock it in the Workbench before playing.');
    $('gameLayout').classList.remove('hidden');
    updateCharacterPanel();
    let navigationWarning = '';
    try { await loadNavigation(); } catch (error) { state.navigation = null; navigationWarning = ` Navigation links could not be loaded: ${error.message}`; }
    renderMap();
    notice(`Loaded ${state.character.name} into ${state.universe.name||'the assigned universe'}.${navigationWarning}`, navigationWarning ? 'error' : 'success');
  }
  start().catch(error=>{notice(`Could not enter gameplay: ${error.message}`,'error');setStatus('Gameplay initialization failed.');});
})();
