(() => {
  'use strict';
  if (window.CORE_DIAGNOSTICS) return;
  const MAX_EVENTS = 100;
  const events = [];
  const scenes = new Map();
  const safe = value => {
    try { return JSON.parse(JSON.stringify(value, (key, item) => {
      if (typeof item === 'function') return `[Function ${item.name || 'anonymous'}]`;
      if (item instanceof Error) return {name:item.name,message:item.message,stack:item.stack};
      return item;
    })); } catch (_) { return String(value); }
  };
  const push = (type, detail = {}) => {
    events.push({time:new Date().toISOString(),type,detail:safe(detail)});
    if (events.length > MAX_EVENTS) events.splice(0, events.length - MAX_EVENTS);
  };
  const vector = v => v && Number.isFinite(v.x) ? {x:v.x,y:v.y,z:v.z} : null;
  const snapshotScene = (scene, label) => {
    if (!scene) return null;
    let active = [];
    try { const smart = scene.getActiveMeshes?.(); active = (smart?.data || []).slice(0, smart?.length || 0).filter(Boolean).map(m => m.name).slice(0,300); } catch (_) {}
    const camera = scene.activeCamera;
    const engine = scene.getEngine?.();
    const projectionMatrix = camera && engine && window.BABYLON
      ? camera.getViewMatrix().multiply(camera.getProjectionMatrix()) : null;
    const viewport = camera && engine ? camera.viewport.toGlobal(engine.getRenderWidth(), engine.getRenderHeight()) : null;
    const meshes = (scene.meshes || []).slice(0,500).map(mesh => {
      const bounds = mesh.getBoundingInfo?.();
      const center = bounds?.boundingSphere?.centerWorld || mesh.getAbsolutePosition?.() || mesh.position;
      let screenProjection = null;
      let inCameraFrustum = null;
      try {
        if (projectionMatrix && viewport && window.BABYLON) {
          const projected = BABYLON.Vector3.Project(center, BABYLON.Matrix.Identity(), projectionMatrix, viewport);
          screenProjection = {x:projected.x,y:projected.y,z:projected.z,insideScreen:projected.x>=0&&projected.x<=engine.getRenderWidth()&&projected.y>=0&&projected.y<=engine.getRenderHeight()};
          inCameraFrustum = camera.isInFrustum(bounds.boundingBox);
        }
      } catch (_) {}
      return {
        name:mesh.name,id:mesh.id,visible:mesh.isVisible,enabled:mesh.isEnabled?.(),visibility:mesh.visibility,
        position:vector(mesh.getAbsolutePosition?.() || mesh.position),scaling:vector(mesh.scaling),
        bounds:bounds ? {center:vector(center),radius:bounds.boundingSphere.radiusWorld,minimum:vector(bounds.boundingBox.minimumWorld),maximum:vector(bounds.boundingBox.maximumWorld)} : null,
        screenProjection,inCameraFrustum,metadata:mesh.metadata||null,
        material:mesh.material ? {name:mesh.material.name,alpha:mesh.material.alpha,wireframe:mesh.material.wireframe,
          diffuseColor:mesh.material.diffuseColor?.toString?.()||null,emissiveColor:mesh.material.emissiveColor?.toString?.()||null,
          disableLighting:mesh.material.disableLighting,disableDepthWrite:mesh.material.disableDepthWrite,disableDepthTest:mesh.material.disableDepthTest,
          depthFunction:mesh.material.depthFunction,transparencyMode:mesh.material.transparencyMode} : null,
        renderingGroupId:mesh.renderingGroupId,layerMask:mesh.layerMask
      };
    });
    return {label,ready:scene.isReady?.(),meshCount:scene.meshes?.length ?? null,activeMeshNames:active,
      camera:camera ? {name:camera.name,position:vector(camera.position),globalPosition:vector(camera.globalPosition),
        target:vector(camera.getTarget?.()),direction:vector(camera.getForwardRay?.().direction),fov:camera.fov,
        minZ:camera.minZ,maxZ:camera.maxZ,mode:camera.mode} : null,meshes};
  };
  const api = window.CORE_DIAGNOSTICS = {
    version:'1',
    captureScene(scene,label='scene') { if (scene) scenes.set(label,scene); },
    event(type,detail={}) { push(type,detail); },
    collect() {
      const canvases = [...document.querySelectorAll('canvas')].map(c => ({id:c.id,width:c.width,height:c.height,clientWidth:c.clientWidth,clientHeight:c.clientHeight,rect:(()=>{const r=c.getBoundingClientRect();return{x:r.x,y:r.y,width:r.width,height:r.height};})()}));
      const gl = (() => { const c=document.querySelector('canvas'); if(!c)return null; try { const g=c.getContext('webgl2')||c.getContext('webgl'); return g?{version:g.getParameter(g.VERSION),renderer:g.getParameter(g.RENDERER),vendor:g.getParameter(g.VENDOR),contextLost:g.isContextLost()}:null; } catch(e){return {error:String(e)};} })();
      return {report_id:crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(16).slice(2)}`,
        created_at:new Date().toISOString(),core_version:document.querySelector('.build-tag')?.textContent?.trim()||'unknown',
        client:{url:location.pathname, userAgent:navigator.userAgent,language:navigator.language,platform:navigator.platform,
          viewport:{width:innerWidth,height:innerHeight,devicePixelRatio},online:navigator.onLine,visibility:document.visibilityState,
          memory:performance.memory?{usedJSHeapSize:performance.memory.usedJSHeapSize,totalJSHeapSize:performance.memory.totalJSHeapSize}:null,
          canvases,webgl:gl},
        character_id:document.body.dataset.characterId||'',
        diagnostics:{events:[...events],scenes:[...scenes.entries()].map(([label,scene])=>snapshotScene(scene,label)),
          page:{view:window.CORE_GAME_STATE?.view||null,notice:document.getElementById('notice')?.textContent||'',
            scripts:[...document.scripts].map(s=>({src:s.src||'inline',loaded:!!s.src&&!!s.sheet})).filter(s=>s.src!=='inline')}}};
    },
    async download() { const report=api.collect(); const blob=new Blob([JSON.stringify(report,null,2)],{type:'application/json'}); const url=URL.createObjectURL(blob); const a=document.createElement('a');a.href=url;a.download=`CORE-diagnostics-${report.report_id}.json`;a.click();setTimeout(()=>URL.revokeObjectURL(url),1500);return report; },
    async submit() {
      const report=api.collect(); const response=await fetch(`${document.body.dataset.apiRoot||''}/api/diagnostics-report.php`,{
        method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CORE-CSRF':document.body.dataset.csrfToken||''},
        body:JSON.stringify(report),credentials:'same-origin'});
      const result=await response.json().catch(()=>({ok:false,error:{message:`Server returned HTTP ${response.status}`}}));
      if(!response.ok||!result.ok)throw new Error(result.error?.message||result.error?.code||`HTTP ${response.status}`);
      push('report_submitted',{report_id:result.report_id});return result;
    }
  };
  window.addEventListener('error',e=>push('window_error',{message:e.message,filename:e.filename,lineno:e.lineno,colno:e.colno,error:e.error?{name:e.error.name,message:e.error.message,stack:e.error.stack}:null}));
  window.addEventListener('unhandledrejection',e=>push('unhandled_rejection',{reason:e.reason instanceof Error?{name:e.reason.name,message:e.reason.message,stack:e.reason.stack}:String(e.reason)}));
  window.addEventListener('webglcontextlost',e=>push('webgl_context_lost',{canvas:e.target?.id||'',statusMessage:e.statusMessage||''}),true);
  window.addEventListener('webglcontextrestored',e=>push('webgl_context_restored',{canvas:e.target?.id||''}),true);
  window.addEventListener('online',()=>push('network_online'));
  window.addEventListener('offline',()=>push('network_offline'));
  document.addEventListener('visibilitychange',()=>push('visibility_change',{state:document.visibilityState}));
  const originalConsoleError=console.error.bind(console);
  console.error=(...args)=>{push('console_error',{arguments:args.map(safe)});originalConsoleError(...args);};
  push('diagnostics_initialized',{href:location.pathname});
  document.addEventListener('DOMContentLoaded',()=>{
    const status=document.getElementById('diagnosticsStatus');
    document.getElementById('diagnosticsDownload')?.addEventListener('click',async()=>{try{await api.download();if(status)status.textContent='Diagnostic JSON downloaded.';}catch(e){if(status)status.textContent=`Download failed: ${e.message}`;}});
    document.getElementById('diagnosticsSubmit')?.addEventListener('click',async()=>{const button=document.getElementById('diagnosticsSubmit');try{button.disabled=true;if(status)status.textContent='Submitting diagnostic report…';const result=await api.submit();if(status)status.textContent=`Saved. Report ID: ${result.report_id}`;}catch(e){if(status)status.textContent=`Submission failed: ${e.message}. Download the JSON and keep it for review.`;}finally{button.disabled=false;}});
  });
})();
