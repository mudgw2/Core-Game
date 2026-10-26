(() => {
  const canvas = document.getElementById('characterPreviewCanvas');
  const status = document.getElementById('characterPreviewStatus');
  const colorInput = document.getElementById('previewColor');
  if (!canvas || !status) return;
  if (!window.BABYLON) { status.textContent = '3D preview unavailable: Babylon.js could not be loaded. The character form still works.'; return; }
  let engine;
  try {
    engine = new BABYLON.Engine(canvas, true, { preserveDrawingBuffer: false, stencil: true, adaptToDeviceRatio: true });
    const scene = new BABYLON.Scene(engine);
    scene.clearColor = new BABYLON.Color4(0.025, 0.045, 0.075, 1);
    const camera = new BABYLON.ArcRotateCamera('characterPreviewCamera', -Math.PI / 2, Math.PI / 2.25, 5.4, new BABYLON.Vector3(0, 1.05, 0), scene);
    camera.attachControl(canvas, true);
    camera.lowerRadiusLimit = 3.2; camera.upperRadiusLimit = 8; camera.wheelPrecision = 45;
    new BABYLON.HemisphericLight('previewAmbient', new BABYLON.Vector3(0, 1, 0), scene).intensity = 0.9;
    const key = new BABYLON.DirectionalLight('previewKey', new BABYLON.Vector3(-0.5, -1, 0.6), scene); key.position = new BABYLON.Vector3(3, 5, -3); key.intensity = 0.7;
    const material = new BABYLON.StandardMaterial('characterMaterial', scene);
    material.diffuseColor = BABYLON.Color3.FromHexString(colorInput ? colorInput.value : '#61d8e8');
    material.specularColor = new BABYLON.Color3(0.25, 0.32, 0.38);
    const dark = new BABYLON.StandardMaterial('jointMaterial', scene); dark.diffuseColor = new BABYLON.Color3(0.12, 0.19, 0.27);
    const parts = [];
    const add = (name, type, position, scaling, mat = material) => {
      const mesh = type === 'sphere' ? BABYLON.MeshBuilder.CreateSphere(name, {diameter: 1, segments: 16}, scene) : BABYLON.MeshBuilder.CreateBox(name, {size: 1}, scene);
      mesh.position = new BABYLON.Vector3(...position); mesh.scaling = new BABYLON.Vector3(...scaling); mesh.material = mat; parts.push(mesh); return mesh;
    };
    // Low-poly humanoid mannequin made entirely from Babylon primitives.
    add('torso', 'box', [0, 1.35, 0], [0.62, 0.82, 0.34]);
    add('head', 'sphere', [0, 2.02, 0], [0.42, 0.48, 0.42]);
    add('pelvis', 'box', [0, 0.83, 0], [0.48, 0.28, 0.3], dark);
    add('leftShoulder', 'sphere', [-0.42, 1.59, 0], [0.22, 0.22, 0.22], dark);
    add('rightShoulder', 'sphere', [0.42, 1.59, 0], [0.22, 0.22, 0.22], dark);
    add('leftArm', 'box', [-0.48, 1.24, 0], [0.19, 0.64, 0.2]);
    add('rightArm', 'box', [0.48, 1.24, 0], [0.19, 0.64, 0.2]);
    add('leftHand', 'sphere', [-0.49, 0.88, 0], [0.17, 0.17, 0.17], dark);
    add('rightHand', 'sphere', [0.49, 0.88, 0], [0.17, 0.17, 0.17], dark);
    add('leftLeg', 'box', [-0.16, 0.43, 0], [0.22, 0.65, 0.24], dark);
    add('rightLeg', 'box', [0.16, 0.43, 0], [0.22, 0.65, 0.24], dark);
    add('leftBoot', 'box', [-0.16, 0.1, 0.08], [0.26, 0.16, 0.38]);
    add('rightBoot', 'box', [0.16, 0.1, 0.08], [0.26, 0.16, 0.38]);
    const ground = BABYLON.MeshBuilder.CreateGround('previewGround', {width: 5, height: 5}, scene); ground.position.y = 0.01;
    const groundMat = new BABYLON.StandardMaterial('previewGroundMaterial', scene); groundMat.diffuseColor = new BABYLON.Color3(0.035, 0.065, 0.1); groundMat.specularColor = BABYLON.Color3.Black(); ground.material = groundMat;
    const ring = BABYLON.MeshBuilder.CreateTorus('previewRing', {diameter: 2.2, thickness: 0.018, tessellation: 64}, scene); ring.rotation.x = Math.PI / 2; ring.position.y = 0.025;
    const ringMat = new BABYLON.StandardMaterial('previewRingMaterial', scene); ringMat.emissiveColor = new BABYLON.Color3(0.1, 0.45, 0.55); ring.material = ringMat;
    if (colorInput) colorInput.addEventListener('input', () => { material.diffuseColor = BABYLON.Color3.FromHexString(colorInput.value); });
    scene.onBeforeRenderObservable.add(() => { parts[0].rotation.y = Math.sin(performance.now() * 0.00045) * 0.025; });
    engine.runRenderLoop(() => scene.render());
    window.addEventListener('resize', () => engine.resize());
    status.textContent = '3D preview ready · primitive mannequin';
  } catch (error) {
    if (engine) engine.dispose();
    status.textContent = '3D preview failed to initialize. The character form still works.';
    console.error('CORE character preview initialization failed', error);
  }
})();
