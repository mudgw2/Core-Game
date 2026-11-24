(() => {
  'use strict';
  if (!window.BABYLON || !BABYLON.SceneLoader) return;

  const script = document.currentScript;
  const modelDirectory = new URL('../assets/models/characters/', script ? script.src : window.location.href).href;

  document.querySelectorAll('.character-portrait-canvas').forEach((canvas, index) => {
    let engine;
    let disposed = false;
    try {
      engine = new BABYLON.Engine(canvas, true, { adaptToDeviceRatio: true });
      const scene = new BABYLON.Scene(engine);
      scene.clearColor = new BABYLON.Color4(.025, .045, .075, 1);
      const camera = new BABYLON.ArcRotateCamera('portrait-camera-' + index, -Math.PI / 2, Math.PI / 2.25, 3.8, new BABYLON.Vector3(0, 1.08, 0), scene);
      camera.attachControl(canvas, true);
      camera.lowerRadiusLimit = 2.4;
      camera.upperRadiusLimit = 7;
      camera.panningSensibility = 0;
      new BABYLON.HemisphericLight('portrait-light-' + index, new BABYLON.Vector3(0, 1, 0), scene).intensity = 1;
      const key = new BABYLON.DirectionalLight('portrait-key-' + index, new BABYLON.Vector3(-.5, -1, .6), scene);
      key.position = new BABYLON.Vector3(3, 5, -3);
      key.intensity = .7;
      const material = new BABYLON.StandardMaterial('portrait-material-' + index, scene);
      const appearance = window.CORE_APPEARANCE ? window.CORE_APPEARANCE.normalize({ tint: canvas.dataset.tint, saturation: canvas.dataset.saturation, brightness: canvas.dataset.brightness }) : { tint: /^#[0-9a-f]{6}$/i.test(canvas.dataset.tint || '') ? canvas.dataset.tint : '#61d8e8' };
      if (window.CORE_APPEARANCE) window.CORE_APPEARANCE.applyToMaterial(material, appearance);
      else material.diffuseColor = BABYLON.Color3.FromHexString(appearance.tint);
      material.specularColor = new BABYLON.Color3(.16, .18, .2);
      material.specularPower = 20;

      BABYLON.SceneLoader.ImportMeshAsync(null, modelDirectory, 'core-humanoid.glb', scene).then((result) => {
        if (disposed) return;
        const importedNodes = [...(result.transformNodes || []), ...(result.meshes || [])];
        const importedRoots = [...new Set(importedNodes)].filter((node) => !node.parent);
        if (!importedRoots.length) throw new Error('The GLB did not contain a usable model root.');
        // Rotate a wrapper around every imported scene root so the armature
        // and its skinned meshes turn together, not just a mesh child.
        const modelRoot = new BABYLON.TransformNode('portrait-model-root-' + index, scene);
        importedRoots.forEach((node) => { node.parent = modelRoot; });
        result.meshes.forEach((mesh) => {
          if (mesh.geometry && mesh.getTotalVertices() > 0) {
            mesh.material = material;
            mesh.isPickable = false;
          }
        });
        modelRoot.rotation = modelRoot.rotation || BABYLON.Vector3.Zero();
        // GLB forward is +Z; the portrait camera views from -Z.
        modelRoot.rotation.y += Math.PI;
        modelRoot.computeWorldMatrix(true);
        let bounds = modelRoot.getHierarchyBoundingVectors(true);
        const height = Math.max(.001, bounds.max.y - bounds.min.y);
        const scale = 1.95 / height;
        modelRoot.scaling = modelRoot.scaling.multiplyByFloats(scale, scale, scale);
        modelRoot.computeWorldMatrix(true);
        bounds = modelRoot.getHierarchyBoundingVectors(true);
        modelRoot.position.x -= (bounds.min.x + bounds.max.x) / 2;
        modelRoot.position.y -= bounds.min.y;
        modelRoot.position.z -= (bounds.min.z + bounds.max.z) / 2;
        modelRoot.computeWorldMatrix(true);
        camera.alpha = -Math.PI / 2;
        camera.beta = Math.PI / 2.05;
        camera.setTarget(new BABYLON.Vector3(0, 1.0, 0));
        camera.radius = 3.15;
        // Match the standalone Walk clip only. A broad /walk/ match can select
        // Crouch_Walk first, producing the wrong portrait animation.
        const walking = result.animationGroups.find((group) =>
          String(group.name || '').trim().toLowerCase() === 'walk'
        );
        if (walking) {
          result.animationGroups.forEach((group) => { if (group !== walking) group.stop(); });
          walking.start(true);
          walking.speedRatio = .55;
        }
      }).catch((error) => {
        console.warn('CORE character GLB portrait failed to load', error);
        const fallback = BABYLON.MeshBuilder.CreateCapsule
          ? BABYLON.MeshBuilder.CreateCapsule('portrait-fallback-' + index, { height: 1.8, radius: .35 }, scene)
          : BABYLON.MeshBuilder.CreateSphere('portrait-fallback-' + index, { diameter: 1 }, scene);
        fallback.position.y = .9;
        fallback.material = material;
      });

      engine.runRenderLoop(() => { if (!disposed) scene.render(); });
      const resize = () => { if (!disposed) engine.resize(); };
      window.addEventListener('resize', resize);
      window.addEventListener('pagehide', () => {
        disposed = true;
        window.removeEventListener('resize', resize);
        engine.dispose();
      }, { once: true });
    } catch (error) {
      if (engine) engine.dispose();
      console.warn('Character portrait preview failed', error);
    }
  });

  document.querySelectorAll('.character-universe-canvas').forEach((canvas, index) => {
    let systems = [];
    try { systems = JSON.parse(canvas.dataset.systems || '[]'); } catch (error) {}
    const system = systems[0];
    if (!system) return;
    let engine;
    try {
      engine = new BABYLON.Engine(canvas, true, { adaptToDeviceRatio: true });
      const scene = new BABYLON.Scene(engine);
      scene.clearColor = new BABYLON.Color4(.015, .03, .055, 1);
      const camera = new BABYLON.ArcRotateCamera('location-camera-' + index, -Math.PI / 2, Math.PI / 3, 15, BABYLON.Vector3.Zero(), scene);
      camera.attachControl(canvas, true);
      camera.lowerRadiusLimit = 7;
      camera.upperRadiusLimit = 30;
      new BABYLON.HemisphericLight('location-light-' + index, new BABYLON.Vector3(0, 1, 0), scene).intensity = .95;
      const star = BABYLON.MeshBuilder.CreateSphere('location-star-' + index, { diameter: 1.7, segments: 20 }, scene);
      const starMat = new BABYLON.StandardMaterial('location-star-mat-' + index, scene);
      starMat.emissiveColor = new BABYLON.Color3(1, .63, .22);
      starMat.diffuseColor = new BABYLON.Color3(.9, .55, .2);
      star.material = starMat;
      const worlds = Array.isArray(system.worlds) ? system.worlds : [];
      const orbitalLayout = window.CORE_ORBITS
        ? window.CORE_ORBITS.layout(worlds, 3.0, 10.5)
        : [];
      orbitalLayout.forEach((entry, i) => {
        const world = entry.world;
        const orbitRadius = entry.radius;
        const orbitPath = [];
        for (let point = 0; point <= 192; point++) {
          const angle = (point / 192) * Math.PI * 2;
          orbitPath.push(new BABYLON.Vector3(Math.cos(angle) * orbitRadius, 0, Math.sin(angle) * orbitRadius));
        }
        const ring = BABYLON.MeshBuilder.CreateTube('location-orbit-' + index + '-' + i, {
          path: orbitPath, radius: .014, tessellation: 8, cap: BABYLON.Mesh.NO_CAP
        }, scene);
        const ringMat = new BABYLON.StandardMaterial('location-orbit-mat-' + index + '-' + i, scene);
        ringMat.emissiveColor = new BABYLON.Color3(.18, .32, .42);
        ringMat.alpha = .6;
        ring.material = ringMat;
        ring.isPickable = false;
        const size = Math.max(.22, Math.min(.72, (Number(world.size) || 3) * .055));
        const planet = BABYLON.MeshBuilder.CreateSphere('location-world-' + index + '-' + i, { diameter: size * 2, segments: 16 }, scene);
        planet.position = new BABYLON.Vector3(Math.cos(entry.phase) * orbitRadius, 0, Math.sin(entry.phase) * orbitRadius);
        const planetMat = new BABYLON.StandardMaterial('location-world-mat-' + index + '-' + i, scene);
        planetMat.diffuseColor = world.type === 'gas_giant' ? new BABYLON.Color3(.72, .48, .28) : new BABYLON.Color3(.25, .63, .76);
        planetMat.emissiveColor = new BABYLON.Color3(.04, .1, .14);
        planet.material = planetMat;
        entry.mesh = planet;
      });
      const started = performance.now();
      engine.runRenderLoop(() => {
        const elapsed = (performance.now() - started) / 1000;
        orbitalLayout.forEach(entry => {
          const mesh = entry?.mesh;
          if (!mesh?.position) return;
          const angle = entry.phase + elapsed * entry.angularSpeed;
          mesh.position.x = Math.cos(angle) * entry.radius;
          mesh.position.z = Math.sin(angle) * entry.radius;
        });
        scene.render();
      });
      const resize = () => engine.resize();
      window.addEventListener('resize', resize);
      window.addEventListener('pagehide', () => { window.removeEventListener('resize', resize); engine.dispose(); }, { once: true });
    } catch (error) {
      if (engine) engine.dispose();
      console.warn('Character location preview failed', error);
    }
  });
})();
