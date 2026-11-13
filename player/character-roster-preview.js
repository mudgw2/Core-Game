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

      const camera = new BABYLON.ArcRotateCamera(
        'portrait-camera-' + index,
        -Math.PI / 2,
        Math.PI / 2.25,
        3.8,
        new BABYLON.Vector3(0, 1.08, 0),
        scene
      );
      camera.attachControl(canvas, true);
      camera.lowerRadiusLimit = 2.4;
      camera.upperRadiusLimit = 7;
      camera.panningSensibility = 0;

      const ambient = new BABYLON.HemisphericLight(
        'portrait-light-' + index,
        new BABYLON.Vector3(0, 1, 0),
        scene
      );
      ambient.intensity = 1.0;

      const key = new BABYLON.DirectionalLight(
        'portrait-key-' + index,
        new BABYLON.Vector3(-.5, -1, .6),
        scene
      );
      key.position = new BABYLON.Vector3(3, 5, -3);
      key.intensity = .7;

      const material = new BABYLON.StandardMaterial('portrait-material-' + index, scene);
      material.diffuseColor = new BABYLON.Color3(.35, .72, .8);
      material.specularColor = new BABYLON.Color3(.16, .18, .2);
      material.specularPower = 20;

      BABYLON.SceneLoader.ImportMeshAsync(
        null,
        modelDirectory,
        'core-humanoid.glb',
        scene
      ).then((result) => {
        if (disposed) return;
        const modelRoot = result.meshes.find((mesh) => !mesh.parent) || result.meshes[0];
        if (!modelRoot) throw new Error('The GLB did not contain a usable model root.');

        result.meshes.forEach((mesh) => {
          if (mesh.geometry && mesh.getTotalVertices() > 0) {
            mesh.material = material;
            mesh.isPickable = false;
          }
        });

        modelRoot.computeWorldMatrix(true);
        const bounds = modelRoot.getHierarchyBoundingVectors(true);
        const height = Math.max(.001, bounds.max.y - bounds.min.y);
        const scale = 2.15 / height;
        modelRoot.scaling = modelRoot.scaling.multiplyByFloats(scale, scale, scale);
        modelRoot.position.x -= ((bounds.min.x + bounds.max.x) / 2) * scale;
        modelRoot.position.y -= ((bounds.min.y + bounds.max.y) / 2) * scale;
        modelRoot.position.z -= ((bounds.min.z + bounds.max.z) / 2) * scale;

        const walking = result.animationGroups.find((group) => /walk/i.test(group.name));
        if (walking) {
          result.animationGroups.forEach((group) => {
            if (group !== walking) group.stop();
          });
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

  // The assigned-system / world flyover remains a separate Babylon.js scene.
  document.querySelectorAll('.character-universe-canvas').forEach((canvas, index) => {
    let systems = [];
    try { systems = JSON.parse(canvas.dataset.systems || '[]'); } catch (error) {}
    const system = systems[0];
    if (!system) return;

    try {
      const engine = new BABYLON.Engine(canvas, true, { adaptToDeviceRatio: true });
      const scene = new BABYLON.Scene(engine);
      scene.clearColor = new BABYLON.Color4(.015, .03, .055, 1);
      const camera = new BABYLON.ArcRotateCamera(
        'location-camera-' + index,
        -Math.PI / 2,
        Math.PI / 3,
        15,
        BABYLON.Vector3.Zero(),
        scene
      );
      camera.attachControl(canvas, true);
      camera.lowerRadiusLimit = 7;
      camera.upperRadiusLimit = 30;
      new BABYLON.HemisphericLight(
        'location-light-' + index,
        new BABYLON.Vector3(0, 1, 0),
        scene
      ).intensity = .95;

      const star = BABYLON.MeshBuilder.CreateSphere(
        'location-star-' + index,
        { diameter: 1.7, segments: 20 },
        scene
      );
      const starMat = new BABYLON.StandardMaterial('location-star-mat-' + index, scene);
      starMat.emissiveColor = new BABYLON.Color3(1, .63, .22);
      starMat.diffuseColor = new BABYLON.Color3(.9, .55, .2);
      star.material = starMat;

      const worlds = Array.isArray(system.worlds) ? system.worlds : [];
      const count = Math.max(worlds.length, 1);
      worlds.forEach((world, i) => {
        const orbit = 2.5 + i * (7.2 / count);
        const ring = BABYLON.MeshBuilder.CreateTorus(
          'location-orbit-' + index + '-' + i,
          { diameter: orbit * 2, thickness: .018, tessellation: 80 },
          scene
        );
        ring.rotation.x = Math.PI / 2.35;
        ring.position.y = -.12;
        const ringMat = new BABYLON.StandardMaterial('location-orbit-mat-' + index + '-' + i, scene);
        ringMat.emissiveColor = new BABYLON.Color3(.18, .32, .42);
        ringMat.alpha = .6;
        ring.material = ringMat;
        ring.isPickable = false;

        const angle = i * 2.399963229728653;
        const size = Math.max(.22, Math.min(.72, (Number(world.size) || 3) * .055));
        const planet = BABYLON.MeshBuilder.CreateSphere(
          'location-world-' + index + '-' + i,
          { diameter: size * 2, segments: 16 },
          scene
        );
        planet.position = new BABYLON.Vector3(Math.cos(angle) * orbit, 0, Math.sin(angle) * orbit * .42);
        const planetMat = new BABYLON.StandardMaterial('location-world-mat-' + index + '-' + i, scene);
        planetMat.diffuseColor = world.type === 'gas_giant'
          ? new BABYLON.Color3(.72, .48, .28)
          : new BABYLON.Color3(.25, .63, .76);
        planetMat.emissiveColor = new BABYLON.Color3(.04, .1, .14);
        planet.material = planetMat;
      });

      engine.runRenderLoop(() => scene.render());
      window.addEventListener('resize', () => engine.resize());
    } catch (error) {
      console.warn('Character location preview failed', error);
    }
  });
})();
