(() => {
  'use strict';

  const canvas = document.getElementById('characterPreviewCanvas');
  const status = document.getElementById('characterPreviewStatus');
  const colorInput = document.getElementById('previewColor');
  const saturationInput = document.getElementById('previewSaturation');
  const brightnessInput = document.getElementById('previewBrightness');
  if (!canvas || !status) return;

  if (!window.BABYLON) {
    status.textContent = '3D preview unavailable: Babylon.js could not be loaded. The character form still works.';
    return;
  }
  if (!BABYLON.SceneLoader) {
    status.textContent = '3D model loader unavailable. The character form still works.';
    return;
  }

  let engine;
  let scene;
  let disposed = false;

  const setStatus = (message) => { if (!disposed) status.textContent = message; };

  try {
    engine = new BABYLON.Engine(canvas, true, {
      preserveDrawingBuffer: false,
      stencil: true,
      adaptToDeviceRatio: true
    });
    scene = new BABYLON.Scene(engine);
    scene.clearColor = new BABYLON.Color4(0.025, 0.045, 0.075, 1);

    const camera = new BABYLON.ArcRotateCamera(
      'characterPreviewCamera',
      -Math.PI / 2,
      Math.PI / 2.25,
      3.8,
      new BABYLON.Vector3(0, 1.12, 0),
      scene
    );
    camera.attachControl(canvas, true);
    camera.lowerRadiusLimit = 2.2;
    camera.upperRadiusLimit = 7.5;
    camera.wheelPrecision = 45;
    camera.panningSensibility = 0;

    const ambient = new BABYLON.HemisphericLight(
      'previewAmbient',
      new BABYLON.Vector3(0, 1, 0),
      scene
    );
    ambient.intensity = 0.95;

    const key = new BABYLON.DirectionalLight(
      'previewKey',
      new BABYLON.Vector3(-0.5, -1, 0.6),
      scene
    );
    key.position = new BABYLON.Vector3(3, 5, -3);
    key.intensity = 0.8;

    const fill = new BABYLON.PointLight(
      'previewFill',
      new BABYLON.Vector3(-2, 2, 2),
      scene
    );
    fill.intensity = 0.35;

    const groundMat = new BABYLON.StandardMaterial('previewGroundMaterial', scene);
    groundMat.diffuseColor = new BABYLON.Color3(0.035, 0.065, 0.1);
    groundMat.specularColor = BABYLON.Color3.Black();

    const ground = BABYLON.MeshBuilder.CreateGround(
      'previewGround',
      { width: 5, height: 5 },
      scene
    );
    ground.position.y = -0.015;
    ground.material = groundMat;

    const ring = BABYLON.MeshBuilder.CreateTorus(
      'previewRing',
      { diameter: 2.2, thickness: 0.018, tessellation: 64 },
      scene
    );
    ring.rotation.x = Math.PI / 2;
    ring.position.y = 0.005;
    const ringMat = new BABYLON.StandardMaterial('previewRingMaterial', scene);
    ringMat.emissiveColor = new BABYLON.Color3(0.1, 0.45, 0.55);
    ring.material = ringMat;

    const characterMaterial = new BABYLON.StandardMaterial('coreHumanoidPreviewMaterial', scene);
    characterMaterial.specularColor = new BABYLON.Color3(0.18, 0.2, 0.22);
    characterMaterial.specularPower = 24;
    const readAppearance = () => ({
      tint: colorInput?.value || '#61d8e8',
      saturation: Number(saturationInput?.value ?? 100),
      brightness: Number(brightnessInput?.value ?? 100)
    });
    const updateAppearance = () => {
      if (window.CORE_APPEARANCE) window.CORE_APPEARANCE.applyToMaterial(characterMaterial, readAppearance());
      else characterMaterial.diffuseColor = BABYLON.Color3.FromHexString(readAppearance().tint);
    };
    updateAppearance();
    [colorInput, saturationInput, brightnessInput].filter(Boolean).forEach(input => input.addEventListener('input', updateAppearance));

    const scriptUrl = document.currentScript ? document.currentScript.src : window.location.href;
    const modelDirectory = new URL('../assets/models/characters/', scriptUrl).href;

    const createPrimitiveFallback = () => {
      const body = BABYLON.CreateSphere
        ? BABYLON.CreateSphere('preview-body', { diameter: 1 }, scene)
        : BABYLON.MeshBuilder.CreateSphere('preview-body', { diameter: 1 }, scene);
      body.scaling = new BABYLON.Vector3(.48, .72, .28);
      body.position.y = 1.05;
      body.material = characterMaterial;
      const head = BABYLON.MeshBuilder.CreateSphere('preview-head', { diameter: .48, segments: 16 }, scene);
      head.position.y = 1.72;
      head.material = characterMaterial;
      const armL = BABYLON.MeshBuilder.CreateCylinder('preview-arm-left', { height: .78, diameter: .17 }, scene);
      armL.position.set(-.42, 1.05, 0); armL.rotation.z = -.12; armL.material = characterMaterial;
      const armR = BABYLON.MeshBuilder.CreateCylinder('preview-arm-right', { height: .78, diameter: .17 }, scene);
      armR.position.set(.42, 1.05, 0); armR.rotation.z = .12; armR.material = characterMaterial;
      const floor = BABYLON.MeshBuilder.CreateGround('preview-fallback-ground', { width: 5, height: 5 }, scene);
      floor.position.y = -.015; floor.material = groundMat;
      const marker = BABYLON.MeshBuilder.CreateTorus('preview-fallback-ring', { diameter: 2.2, thickness: .018 }, scene);
      marker.rotation.x = Math.PI / 2; marker.position.y = .005;
      const markerMaterial = new BABYLON.StandardMaterial('preview-fallback-ring-material', scene);
      markerMaterial.emissiveColor = new BABYLON.Color3(.1, .45, .55); marker.material = markerMaterial;
    };

    setStatus('Loading standing humanoid model…');

    BABYLON.SceneLoader.ImportMeshAsync(
      null,
      modelDirectory,
      'core-humanoid.glb',
      scene
    ).then((result) => {
      if (disposed) return;

      const importedNodes = [...(result.transformNodes || []), ...(result.meshes || [])];
      const importedRoots = [...new Set(importedNodes)].filter((node) => !node.parent);
      if (!importedRoots.length) throw new Error('The GLB did not contain a usable model root.');
      // Rotate a wrapper around all imported roots so the armature and every
      // skinned mesh turn together; rotating a mesh-only root is insufficient.
      const modelRoot = new BABYLON.TransformNode('character-preview-model-root', scene);
      importedRoots.forEach((node) => { node.parent = modelRoot; });

      result.meshes.forEach((mesh) => {
        if (mesh.geometry && mesh.getTotalVertices() > 0) {
          mesh.material = characterMaterial;
          mesh.isPickable = false;
        }
      });

      // The supplied Mixamo GLB presents its front along +Z. The preview camera
      // is on -Z, so turn the complete rig toward the camera before framing it.
      modelRoot.rotation = modelRoot.rotation || BABYLON.Vector3.Zero();
      modelRoot.rotation.y += Math.PI;
      modelRoot.computeWorldMatrix(true);
      let bounds = modelRoot.getHierarchyBoundingVectors(true);
      const height = Math.max(0.001, bounds.max.y - bounds.min.y);
      const scale = 2.05 / height;
      modelRoot.scaling = modelRoot.scaling.multiplyByFloats(scale, scale, scale);
      modelRoot.computeWorldMatrix(true);
      bounds = modelRoot.getHierarchyBoundingVectors(true);
      // Keep the feet on the preview floor and center the body horizontally.
      modelRoot.position.x -= (bounds.min.x + bounds.max.x) / 2;
      modelRoot.position.y -= bounds.min.y;
      modelRoot.position.z -= (bounds.min.z + bounds.max.z) / 2;
      modelRoot.computeWorldMatrix(true);

      camera.alpha = -Math.PI / 2;
      camera.beta = Math.PI / 2.05;
      camera.setTarget(new BABYLON.Vector3(0, 1.0, 0));
      camera.radius = 3.25;

      // Character creation is a presentation/inspection view, not a movement
      // demo. Prefer a neutral idle/standing clip; otherwise leave the rig in
      // its imported rest pose. In particular, never start the walking clip.
      const idle = result.animationGroups.find((group) =>
        /^idle_a$/i.test(group.name)
      ) || result.animationGroups.find((group) =>
        /^idle_subtle$/i.test(group.name)
      ) || result.animationGroups.find((group) =>
        /^rest_pose$/i.test(group.name)
      );
      result.animationGroups.forEach((group) => group.stop());
      if (idle) {
        idle.start(true, 1, idle.from, idle.to, false);
        setStatus('Humanoid ready · Neutral standing idle');
      } else {
        setStatus('Humanoid ready · Neutral rest pose');
      }
    }).catch((error) => {
      console.error('CORE humanoid GLB load failed', error);
      createPrimitiveFallback();
      setStatus('Humanoid model unavailable · using primitive preview');
    });

    engine.runRenderLoop(() => {
      if (!disposed && scene) scene.render();
    });

    const resize = () => { if (engine && !disposed) engine.resize(); };
    window.addEventListener('resize', resize);
    window.addEventListener('pagehide', () => {
      disposed = true;
      window.removeEventListener('resize', resize);
      if (engine) engine.dispose();
    }, { once: true });
  } catch (error) {
    if (engine) engine.dispose();
    console.error('CORE character preview initialization failed', error);
    setStatus('3D preview failed to initialize. The character form still works.');
  }
})();
