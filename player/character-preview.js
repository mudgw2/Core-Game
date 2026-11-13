(() => {
  'use strict';

  const canvas = document.getElementById('characterPreviewCanvas');
  const status = document.getElementById('characterPreviewStatus');
  const colorInput = document.getElementById('previewColor');
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

    const skinMaterial = new BABYLON.StandardMaterial('coreHumanoidPreviewMaterial', scene);
    skinMaterial.diffuseColor = BABYLON.Color3.FromHexString(
      colorInput ? colorInput.value : '#61d8e8'
    );
    skinMaterial.specularColor = new BABYLON.Color3(0.18, 0.2, 0.22);
    skinMaterial.specularPower = 24;

    if (colorInput) {
      colorInput.addEventListener('input', () => {
        skinMaterial.diffuseColor = BABYLON.Color3.FromHexString(colorInput.value);
      });
    }

    const scriptUrl = document.currentScript ? document.currentScript.src : window.location.href;
    const modelDirectory = new URL('../assets/models/characters/', scriptUrl).href;

    setStatus('Loading animated humanoid model…');

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
          mesh.material = skinMaterial;
          mesh.isPickable = false;
        }
      });

      modelRoot.computeWorldMatrix(true);
      const bounds = modelRoot.getHierarchyBoundingVectors(true);
      const height = Math.max(0.001, bounds.max.y - bounds.min.y);
      const scale = 2.25 / height;
      modelRoot.scaling = modelRoot.scaling.multiplyByFloats(scale, scale, scale);
      modelRoot.position.y -= ((bounds.min.y + bounds.max.y) / 2) * scale;
      modelRoot.position.x -= ((bounds.min.x + bounds.max.x) / 2) * scale;
      modelRoot.position.z -= ((bounds.min.z + bounds.max.z) / 2) * scale;
      modelRoot.rotation = modelRoot.rotation || BABYLON.Vector3.Zero();

      camera.setTarget(new BABYLON.Vector3(0, 1.08, 0));
      camera.radius = 3.8;

      const walking = result.animationGroups.find((group) =>
        /walk/i.test(group.name)
      );
      if (walking) {
        result.animationGroups.forEach((group) => {
          if (group !== walking) group.stop();
        });
        walking.start(true, 1, walking.from, walking.to, false);
        walking.speedRatio = 0.65;
        setStatus('Animated humanoid ready · Walking preview');
      } else {
        setStatus('Humanoid model ready · no Walking clip found');
      }
    }).catch((error) => {
      console.error('CORE humanoid GLB load failed', error);
      setStatus('Could not load the humanoid model. The character form still works.');
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
