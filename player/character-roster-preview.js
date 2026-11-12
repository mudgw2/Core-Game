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
    let systems=[];try{systems=JSON.parse(canvas.dataset.systems||'[]')}catch{}
    const system=systems[0];if(!system)return;
    try{
      const engine=new BABYLON.Engine(canvas,true,{adaptToDeviceRatio:true});
      const scene=new BABYLON.Scene(engine);scene.clearColor=new BABYLON.Color4(.015,.03,.055,1);
      const camera=new BABYLON.ArcRotateCamera('location-camera-'+index,-Math.PI/2,Math.PI/3,15,BABYLON.Vector3.Zero(),scene);
      camera.attachControl(canvas,true);camera.lowerRadiusLimit=7;camera.upperRadiusLimit=30;
      new BABYLON.HemisphericLight('location-light-'+index,new BABYLON.Vector3(0,1,0),scene).intensity=.95;
      const star=BABYLON.MeshBuilder.CreateSphere('location-star-'+index,{diameter:1.7,segments:20},scene);
      const starMat=new BABYLON.StandardMaterial('location-star-mat-'+index,scene);starMat.emissiveColor=new BABYLON.Color3(1,.63,.22);starMat.diffuseColor=new BABYLON.Color3(.9,.55,.2);star.material=starMat;
      const worlds=Array.isArray(system.worlds)?system.worlds:[];
      const count=Math.max(worlds.length,1);
      worlds.forEach((world,i)=>{
        const orbit=2.5+i*(7.2/count);
        const ring=BABYLON.MeshBuilder.CreateTorus('location-orbit-'+index+'-'+i,{diameter:orbit*2,thickness:.018,tessellation:80},scene);
        ring.rotation.x=Math.PI/2.35;ring.position.y=-.12;const rm=new BABYLON.StandardMaterial('location-orbit-mat-'+index+'-'+i,scene);rm.emissiveColor=new BABYLON.Color3(.18,.32,.42);rm.alpha=.6;ring.material=rm;ring.isPickable=false;
        const angle=i*2.399963229728653;
        const size=Math.max(.22,Math.min(.72,(Number(world.size)||3)*.055));
        const planet=BABYLON.MeshBuilder.CreateSphere('location-world-'+index+'-'+i,{diameter:size*2,segments:16},scene);
        planet.position=new BABYLON.Vector3(Math.cos(angle)*orbit,0,Math.sin(angle)*orbit*.42);
        const pm=new BABYLON.StandardMaterial('location-world-mat-'+index+'-'+i,scene);
        pm.diffuseColor=world.type==='gas_giant'?new BABYLON.Color3(.72,.48,.28):new BABYLON.Color3(.25,.63,.76);
        pm.emissiveColor=new BABYLON.Color3(.04,.1,.14);planet.material=pm;
      });
      engine.runRenderLoop(()=>scene.render());window.addEventListener('resize',()=>engine.resize());
    }catch(e){console.warn('Character location preview failed',e)}
  });
})();
