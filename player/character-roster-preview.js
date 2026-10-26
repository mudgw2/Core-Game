(() => {
  'use strict';
  if (!window.BABYLON) return;
  document.querySelectorAll('.character-portrait-canvas').forEach((canvas, index) => {
    try {
      const engine = new BABYLON.Engine(canvas, true, { adaptToDeviceRatio: true });
      const scene = new BABYLON.Scene(engine); scene.clearColor = new BABYLON.Color4(.025,.045,.075,1);
      const camera = new BABYLON.ArcRotateCamera('portrait-camera-'+index, -Math.PI/2, Math.PI/2.25, 4.8, new BABYLON.Vector3(0,1,0), scene); camera.attachControl(canvas,true); camera.lowerRadiusLimit=3.2; camera.upperRadiusLimit=7;
      new BABYLON.HemisphericLight('portrait-light-'+index,new BABYLON.Vector3(0,1,0),scene).intensity=.95;
      const mat=new BABYLON.StandardMaterial('portrait-mat-'+index,scene); mat.diffuseColor=new BABYLON.Color3(.22,.68,.78); mat.emissiveColor=new BABYLON.Color3(.04,.12,.15);
      const dark=new BABYLON.StandardMaterial('portrait-dark-'+index,scene); dark.diffuseColor=new BABYLON.Color3(.12,.2,.3);
      const box=(name,pos,scale,m=mat)=>{const x=BABYLON.MeshBuilder.CreateBox(name+'-'+index,{size:1},scene);x.position=new BABYLON.Vector3(...pos);x.scaling=new BABYLON.Vector3(...scale);x.material=m;};
      const sphere=(name,pos,scale,m=mat)=>{const x=BABYLON.MeshBuilder.CreateSphere(name+'-'+index,{diameter:1,segments:16},scene);x.position=new BABYLON.Vector3(...pos);x.scaling=new BABYLON.Vector3(...scale);x.material=m;};
      box('torso',[0,1.25,0],[.58,.8,.32]); sphere('head',[0,1.94,0],[.42,.48,.42]); box('pelvis',[0,.77,0],[.46,.26,.3],dark); box('armL',[-.43,1.2,0],[.18,.62,.2]); box('armR',[.43,1.2,0],[.18,.62,.2]); box('legL',[-.15,.34,0],[.2,.62,.22],dark); box('legR',[.15,.34,0],[.2,.62,.22],dark);
      engine.runRenderLoop(()=>{scene.render();}); window.addEventListener('resize',()=>engine.resize());
    } catch(e) { console.warn('Character portrait preview failed',e); }
  });
  document.querySelectorAll('.character-universe-canvas').forEach((canvas,index)=>{
    let systems=[]; try{systems=JSON.parse(canvas.dataset.systems||'[]')}catch{}
    if(!systems.length) return;
    try {
      const engine=new BABYLON.Engine(canvas,true,{adaptToDeviceRatio:true}); const scene=new BABYLON.Scene(engine); scene.clearColor=new BABYLON.Color4(.015,.03,.055,1);
      const camera=new BABYLON.ArcRotateCamera('universe-camera-'+index,-Math.PI/2,Math.PI/3,30,BABYLON.Vector3.Zero(),scene); camera.attachControl(canvas,true); camera.lowerRadiusLimit=5; camera.upperRadiusLimit=100;
      new BABYLON.HemisphericLight('universe-light-'+index,new BABYLON.Vector3(0,1,0),scene).intensity=.9;
      const finite=v=>Number.isFinite(Number(v))?Number(v):0; const minX=Math.min(...systems.map(s=>finite(s.x))),maxX=Math.max(...systems.map(s=>finite(s.x))),minY=Math.min(...systems.map(s=>finite(s.y))),maxY=Math.max(...systems.map(s=>finite(s.y))); const span=Math.max(maxX-minX,maxY-minY,1),scale=24/span;
      systems.forEach((sys,i)=>{const mesh=BABYLON.MeshBuilder.CreateSphere('roster-system-'+index+'-'+i,{diameter:i===0?1.15:.35},scene);mesh.position=new BABYLON.Vector3((finite(sys.x)-(minX+maxX)/2)*scale,0,(finite(sys.y)-(minY+maxY)/2)*scale);const m=new BABYLON.StandardMaterial('roster-system-mat-'+index+'-'+i,scene);const c=sys.classification==='anomalous'?new BABYLON.Color3(1,.35,.22):sys.classification==='nebula-adjacent'?new BABYLON.Color3(.65,.4,1):sys.classification==='binary'?new BABYLON.Color3(.95,.72,.3):new BABYLON.Color3(.2,.75,.95);m.emissiveColor=c;mesh.material=m;});
      engine.runRenderLoop(()=>scene.render());window.addEventListener('resize',()=>engine.resize());
    }catch(e){console.warn('Universe roster preview failed',e)}
  });
})();
