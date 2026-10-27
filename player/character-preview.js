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
    const camera = new BABYLON.ArcRotateCamera('characterPreviewCamera', -Math.PI / 2, Math.PI / 2.25, 4.8, new BABYLON.Vector3(0, 1.02, 0), scene);
    camera.attachControl(canvas, true); camera.lowerRadiusLimit = 3.2; camera.upperRadiusLimit = 7.5; camera.wheelPrecision = 45;
    new BABYLON.HemisphericLight('previewAmbient', new BABYLON.Vector3(0, 1, 0), scene).intensity = 1.05;
    const key = new BABYLON.DirectionalLight('previewKey', new BABYLON.Vector3(-0.5, -1, 0.6), scene); key.position = new BABYLON.Vector3(3, 5, -3); key.intensity = 0.72;
    const skin = new BABYLON.StandardMaterial('skinMaterial', scene);
    skin.diffuseColor = BABYLON.Color3.FromHexString(colorInput ? colorInput.value : '#d8a995');
    skin.specularColor = new BABYLON.Color3(0.12, 0.1, 0.09); skin.specularPower = 12;
    const shorts = new BABYLON.StandardMaterial('shortsMaterial', scene); shorts.diffuseColor = new BABYLON.Color3(0.075, 0.085, 0.09); shorts.specularColor = BABYLON.Color3.Black();
    const groundMat = new BABYLON.StandardMaterial('previewGroundMaterial', scene); groundMat.diffuseColor = new BABYLON.Color3(0.035, 0.065, 0.1); groundMat.specularColor = BABYLON.Color3.Black();
    const parts = [];
    const ellipsoid = (name, pos, scale, material = skin, segments = 7) => {
      const mesh = BABYLON.MeshBuilder.CreateSphere(name, {diameter: 1, segments}, scene);
      mesh.position.set(...pos); mesh.scaling.set(...scale); mesh.material = material; parts.push(mesh); return mesh;
    };
    const link = (name, a, b, radiusA, radiusB, material = skin, tessellation = 7) => {
      const va = new BABYLON.Vector3(...a), vb = new BABYLON.Vector3(...b), delta = vb.subtract(va);
      const mesh = BABYLON.MeshBuilder.CreateCylinder(name, {height: delta.length(), diameterTop: radiusB * 2, diameterBottom: radiusA * 2, tessellation, subdivisions: 1}, scene);
      mesh.position = va.add(vb).scale(0.5);
      // Rotate the cylinder's Y axis to match the joint-to-joint direction using Babylon's stable axis-angle API.
      const direction = delta.normalize();
      const up = BABYLON.Axis.Y;
      const axis = BABYLON.Vector3.Cross(up, direction);
      const dot = BABYLON.Scalar.Clamp(BABYLON.Vector3.Dot(up, direction), -1, 1);
      if (axis.lengthSquared() < 1e-8) {
        mesh.rotationQuaternion = dot < 0 ? BABYLON.Quaternion.RotationAxis(BABYLON.Axis.X, Math.PI) : BABYLON.Quaternion.Identity();
      } else {
        axis.normalize();
        mesh.rotationQuaternion = BABYLON.Quaternion.RotationAxis(axis, Math.acos(dot));
      }
      mesh.material = material; parts.push(mesh); return mesh;
    };
    // Faceted, athletic humanoid placeholder: bare upper body and dark shorts, based on the supplied reference.
    // Head is intentionally featureless and angular so it remains a reusable default for different species.
    ellipsoid('cranium', [0, 2.12, 0], [0.34, 0.43, 0.31], skin, 6);
    ellipsoid('jaw', [0, 1.91, 0.025], [0.25, 0.25, 0.25], skin, 6);
    link('neck', [0, 1.72, 0], [0, 1.91, 0], 0.105, 0.12);
    ellipsoid('chest', [0, 1.48, 0], [0.53, 0.38, 0.29], skin, 7);
    ellipsoid('abdomen', [0, 1.17, 0.015], [0.34, 0.39, 0.23], skin, 7);
    ellipsoid('pelvis', [0, 0.88, 0], [0.38, 0.23, 0.25], skin, 7);
    ellipsoid('shortsWaist', [0, 0.82, 0], [0.4, 0.22, 0.27], shorts, 7);
    ellipsoid('shortsLeftLeg', [-0.205, 0.68, 0.005], [0.205, 0.25, 0.28], shorts, 6);
    ellipsoid('shortsRightLeg', [0.205, 0.68, 0.005], [0.205, 0.25, 0.28], shorts, 6);
    // Shoulders, tapered arms, elbows, forearms and simplified hands.
    ellipsoid('leftShoulder', [-0.48, 1.56, 0], [0.23, 0.23, 0.23]); ellipsoid('rightShoulder', [0.48, 1.56, 0], [0.23, 0.23, 0.23]);
    link('leftUpperArm', [-0.5, 1.52, 0], [-0.62, 1.19, 0.015], 0.16, 0.105);
    link('rightUpperArm', [0.5, 1.52, 0], [0.62, 1.19, 0.015], 0.16, 0.105);
    ellipsoid('leftElbow', [-0.62, 1.19, 0.015], [0.11, 0.12, 0.12]); ellipsoid('rightElbow', [0.62, 1.19, 0.015], [0.11, 0.12, 0.12]);
    link('leftForearm', [-0.62, 1.19, 0.015], [-0.7, 0.88, 0.055], 0.105, 0.065);
    link('rightForearm', [0.62, 1.19, 0.015], [0.7, 0.88, 0.055], 0.105, 0.065);
    ellipsoid('leftHand', [-0.71, 0.79, 0.06], [0.085, 0.14, 0.075]); ellipsoid('rightHand', [0.71, 0.79, 0.06], [0.085, 0.14, 0.075]);
    // Long, tapered legs with visible knees, shins and simple bare feet.
    link('leftThigh', [-0.2, 0.56, 0], [-0.19, 0.28, 0.015], 0.145, 0.105);
    link('rightThigh', [0.2, 0.56, 0], [0.19, 0.28, 0.015], 0.145, 0.105);
    ellipsoid('leftKnee', [-0.19, 0.28, 0.015], [0.105, 0.12, 0.11]); ellipsoid('rightKnee', [0.19, 0.28, 0.015], [0.105, 0.12, 0.11]);
    link('leftShin', [-0.19, 0.25, 0.015], [-0.22, 0.075, 0.025], 0.095, 0.065);
    link('rightShin', [0.19, 0.25, 0.015], [0.22, 0.075, 0.025], 0.095, 0.065);
    ellipsoid('leftFoot', [-0.22, 0.045, 0.11], [0.105, 0.055, 0.19]); ellipsoid('rightFoot', [0.22, 0.045, 0.11], [0.105, 0.055, 0.19]);
    const ground = BABYLON.MeshBuilder.CreateGround('previewGround', {width: 5, height: 5}, scene); ground.position.y = -0.015; ground.material = groundMat;
    const ring = BABYLON.MeshBuilder.CreateTorus('previewRing', {diameter: 2.2, thickness: 0.018, tessellation: 48}, scene); ring.rotation.x = Math.PI / 2; ring.position.y = 0.005;
    const ringMat = new BABYLON.StandardMaterial('previewRingMaterial', scene); ringMat.emissiveColor = new BABYLON.Color3(0.1, 0.45, 0.55); ring.material = ringMat;
    if (colorInput) colorInput.addEventListener('input', () => { skin.diffuseColor = BABYLON.Color3.FromHexString(colorInput.value); });
    scene.onBeforeRenderObservable.add(() => { const sway = Math.sin(performance.now() * 0.00045) * 0.012; parts.forEach(p => { if (p.name === 'chest') p.rotation.z = sway; }); });
    engine.runRenderLoop(() => scene.render()); window.addEventListener('resize', () => engine.resize());
    status.textContent = '3D preview ready · low-poly humanoid · 0.1.80';
  } catch (error) {
    if (engine) engine.dispose(); status.textContent = '3D preview failed to initialize. The character form still works.'; console.error('CORE character preview initialization failed', error);
  }
})();
