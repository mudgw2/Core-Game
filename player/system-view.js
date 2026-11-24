(() => {
  'use strict';

  const byId = id => document.getElementById(id);
  if (!window.BABYLON) {
    window.CORE_SYSTEM_VIEW = { open() {
      const fallback = byId('systemViewFallback');
      if (fallback) { fallback.classList.remove('hidden'); fallback.textContent = 'Babylon.js is unavailable. Check network access to the Babylon.js CDN.'; }
    }, close() {} };
    return;
  }
  const view = {
    engine: null,
    scene: null,
    canvas: null,
    activeSystemId: null,
    planetMeshes: new Map(),
    moonEntries: [],
    resizeHandler: null,
    observer: null,
    hoveredWorldId: null
  };

  const palettes = {
    gas_giant: new BABYLON.Color3(.76, .48, .27),
    asteroid: new BABYLON.Color3(.48, .49, .46),
    airless: new BABYLON.Color3(.57, .59, .62),
    exotic: new BABYLON.Color3(.55, .39, .78),
    ocean: new BABYLON.Color3(.18, .47, .78),
    desert: new BABYLON.Color3(.76, .57, .32),
    large_terrestrial: new BABYLON.Color3(.35, .63, .42),
    temperate: new BABYLON.Color3(.25, .65, .53)
  };

  function display(value) {
    return value === undefined || value === null || value === '' ? '—' : String(value);
  }

  function selectWorld(world) {
    window.CORE_SELECTED_WORLD = world;
    window.dispatchEvent(new CustomEvent('core:world-selected', { detail: { world } }));
    byId('systemWorldName').textContent = world.name || world.id || 'Unnamed world';
    byId('systemWorldDescription').textContent =
      `${String(world.type || 'unknown').replaceAll('_', ' ')} world · Generated from the universe seed.`;
    byId('systemWorldType').textContent = String(world.type || 'unknown').replaceAll('_', ' ');
    byId('systemWorldSystemSeed').textContent = display(world.system_seed);
    byId('systemWorldSeed').textContent = display(world.seed);
    byId('systemWorldUwp').textContent = display(world.uwp);
    byId('systemWorldStarport').textContent = display(world.starport);
    byId('systemWorldSize').textContent = display(world.size);
    byId('systemWorldAtmosphere').textContent = display(world.atmosphere);
    byId('systemWorldHydrographics').textContent = display(world.hydrographics);
    byId('systemWorldPopulation').textContent = display(world.population);
    byId('systemWorldTech').textContent = display(world.tech_level);

    view.planetMeshes.forEach((mesh, id) => {
      const material = mesh.material;
      if (material && material.emissiveColor) {
        material.emissiveColor = id === world.id
          ? new BABYLON.Color3(.22, .19, .06)
          : new BABYLON.Color3(.025, .035, .045);
      }
    });
  }

  function makeBodyLabel(name, subtitle, parent, scene, meshName, width = 3.5) {
    const texture = new BABYLON.DynamicTexture(`${meshName}-texture`, { width: 512, height: 128 }, scene, false);
    texture.hasAlpha = true;
    texture.vScale = -1;
    texture.vOffset = 1;
    const context = texture.getContext();
    context.clearRect(0, 0, 512, 128);
    context.textAlign = 'center';
    context.font = 'bold 38px Arial';
    context.fillStyle = '#f3f8ff';
    context.shadowColor = '#07111d';
    context.shadowBlur = 10;
    context.fillText(String(name || ''), 256, 53);
    context.font = '24px Arial';
    context.fillStyle = '#b7d0e2';
    context.fillText(String(subtitle || ''), 256, 96);
    texture.update(false);
    const plane = BABYLON.MeshBuilder.CreatePlane(meshName, { width, height: width * .28 }, scene);
    plane.parent = parent;
    plane.position.y = Math.max(.7, parent.getBoundingInfo().boundingSphere.radius * 1.65);
    plane.billboardMode = BABYLON.Mesh.BILLBOARDMODE_ALL;
    const material = new BABYLON.StandardMaterial(`${meshName}-material`, scene);
    material.diffuseTexture = texture;
    material.opacityTexture = texture;
    material.emissiveColor = BABYLON.Color3.White();
    material.disableLighting = true;
    material.backFaceCulling = false;
    material.disableDepthWrite = true;
    material.depthFunction = BABYLON.Engine.ALWAYS;
    plane.material = material;
    plane.renderingGroupId = 2;
    plane.isPickable = false;
    return plane;
  }

  function makeMaterial(name, diffuse, emissive, scene) {
    const material = new BABYLON.StandardMaterial(name, scene);
    material.diffuseColor = diffuse;
    material.emissiveColor = emissive;
    material.specularColor = new BABYLON.Color3(.2, .22, .25);
    material.specularPower = 24;
    return material;
  }

  function clear() {
    if (view.resizeHandler) window.removeEventListener('resize', view.resizeHandler);
    if (view.observer) view.observer.disconnect();
    if (view.engine) {
      view.engine.stopRenderLoop();
      view.engine.dispose();
    }
    view.engine = null;
    view.scene = null;
    view.canvas = null;
    view.resizeHandler = null;
    view.observer = null;
    view.planetMeshes.clear();
    view.moonEntries = [];
    view.activeSystemId = null;
    view.hoveredWorldId = null;
    const hoverLabel = byId('systemViewHoverLabel'); if (hoverLabel) hoverLabel.classList.add('hidden');
  }

  function open(system) {
    if (!window.BABYLON) {
      byId('systemViewFallback').classList.remove('hidden');
      byId('systemViewFallback').textContent = 'Babylon.js is unavailable. Check network access to the Babylon.js CDN.';
      return;
    }

    const canvas = byId('systemViewCanvas');
    if (!canvas) return;
    const worlds = Array.isArray(system.worlds) ? system.worlds : [];
    clear();
    view.canvas = canvas;
    view.activeSystemId = system.id || system.name || 'system';
    byId('systemViewSeed').textContent = display(system.seed || worlds[0]?.system_seed);
    byId('systemViewFallback').classList.add('hidden');

    try {
      view.engine = new BABYLON.Engine(canvas, true, {
        preserveDrawingBuffer: true,
        stencil: true,
        adaptToDeviceRatio: true
      });
      view.scene = new BABYLON.Scene(view.engine);
      window.CORE_DIAGNOSTICS?.captureScene(view.scene, 'system-view');
      view.scene.clearColor = new BABYLON.Color4(.018, .035, .058, 1);

      const camera = new BABYLON.ArcRotateCamera(
        'system-camera',
        -Math.PI / 2.25,
        Math.PI / 2.75,
        24,
        BABYLON.Vector3.Zero(),
        view.scene
      );
      camera.lowerRadiusLimit = 7;
      camera.upperRadiusLimit = 75;
      camera.wheelDeltaPercentage = .012;
      camera.panningSensibility = 55;
      camera.useCtrlForPanning = false;
      camera.panningMouseButton = 2;
      camera.attachControl(canvas, true);

      const ambient = new BABYLON.HemisphericLight('system-ambient', new BABYLON.Vector3(0, 1, 0), view.scene);
      ambient.intensity = .72;
      const key = new BABYLON.PointLight('system-starlight', BABYLON.Vector3.Zero(), view.scene);
      key.intensity = 1.8;
      key.range = 100;

      const star = BABYLON.MeshBuilder.CreateSphere('system-primary-star', {
        diameter: 2.25,
        segments: 32
      }, view.scene);
      const starMaterial = makeMaterial(
        'system-star-material',
        new BABYLON.Color3(.95, .58, .22),
        new BABYLON.Color3(1, .42, .08),
        view.scene
      );
      star.material = starMaterial;
      star.isPickable = false;

      const glow = BABYLON.MeshBuilder.CreateSphere('system-star-glow', {
        diameter: 2.65,
        segments: 24
      }, view.scene);
      const glowMaterial = new BABYLON.StandardMaterial('system-star-glow-material', view.scene);
      glowMaterial.emissiveColor = new BABYLON.Color3(.95, .38, .08);
      glowMaterial.alpha = .12;
      glowMaterial.disableLighting = true;
      glow.material = glowMaterial;
      glow.isPickable = false;

      const orbitalLayout = window.CORE_ORBITS
        ? window.CORE_ORBITS.layout(worlds, 4.0, 23.0)
        : [];
      const planetEntries = [];

      orbitalLayout.forEach((entry, index) => {
        const world = entry.world;
        const orbitRadius = entry.radius;
        // Current world records define orbital order, not inclination or
        // eccentricity. Render consistent coplanar paths instead of inventing
        // a different tilted plane for every planet.
        const orbitPath = [];
        for (let point = 0; point <= 256; point++) {
          const angle = (point / 256) * Math.PI * 2;
          orbitPath.push(new BABYLON.Vector3(
            Math.cos(angle) * orbitRadius,
            0,
            Math.sin(angle) * orbitRadius
          ));
        }
        const orbit = BABYLON.MeshBuilder.CreateTube(`orbit-${index}`, {
          path: orbitPath,
          radius: .018,
          tessellation: 8,
          cap: BABYLON.Mesh.NO_CAP,
          updatable: false
        }, view.scene);
        orbit.material = makeMaterial(
          `orbit-material-${index}`,
          new BABYLON.Color3(.12, .22, .3),
          new BABYLON.Color3(.07, .15, .22),
          view.scene
        );
        orbit.isPickable = false;

        const sizeValue = Math.max(0, Math.min(10, Number(world.size) || 3));
        const baseRadius = world.type === 'gas_giant'
          ? .55 + sizeValue * .055
          : .2 + sizeValue * .045;
        const planet = BABYLON.MeshBuilder.CreateSphere(`world-${world.id || index}`, {
          diameter: baseRadius * 2,
          segments: 24
        }, view.scene);
        planet.position = new BABYLON.Vector3(
          Math.cos(entry.phase) * orbitRadius,
          0,
          Math.sin(entry.phase) * orbitRadius
        );
        const color = palettes[world.type] || palettes.temperate;
        planet.material = makeMaterial(
          `world-material-${index}`,
          color,
          new BABYLON.Color3(.025, .035, .045),
          view.scene
        );
        planet.metadata = { world };
        view.planetMeshes.set(world.id, planet);
        makeBodyLabel(world.name || `World ${String(index + 1).padStart(2, '0')}`, `UWP ${world.uwp || '—'}`, planet, view.scene, `world-label-${index}`, 4.2);
        planetEntries.push({ mesh: planet, phase: entry.phase, radius: orbitRadius, world, speed: entry.angularSpeed });

        // Natural satellites are rendered as child-orbiting bodies. Their real
        // orbital radii are compressed for legibility at system-view scale.
        const moons = Array.isArray(world.moons) ? world.moons : [];
        moons.forEach((moon, moonIndex) => {
          if (!moon || typeof moon !== 'object') return;
          const satelliteOrbitRadius = baseRadius * Math.max(1.8, Math.min(4.5,
            (Number(moon.orbital_radius_planet_radii) || (2.2 + moonIndex * .7)) * .12));
          const satellitePhase = (Number(moon.phase_degrees) || 0) * Math.PI / 180;
          const satellitePath = [];
          for (let point = 0; point <= 64; point++) {
            const angle = (point / 64) * Math.PI * 2;
            satellitePath.push(new BABYLON.Vector3(
              Math.cos(angle) * satelliteOrbitRadius, 0,
              Math.sin(angle) * satelliteOrbitRadius
            ));
          }
          const satelliteOrbit = BABYLON.MeshBuilder.CreateTube(
            `moon-orbit-${index}-${moonIndex}`, {
              path: satellitePath, radius: .009, tessellation: 6,
              cap: BABYLON.Mesh.NO_CAP, updatable: false
            }, view.scene
          );
          const orbitMaterial = new BABYLON.StandardMaterial(`moon-orbit-material-${index}-${moonIndex}`, view.scene);
          orbitMaterial.disableLighting = true;
          orbitMaterial.emissiveColor = new BABYLON.Color3(.16, .23, .29);
          orbitMaterial.alpha = .72;
          satelliteOrbit.material = orbitMaterial;
          satelliteOrbit.parent = planet;
          satelliteOrbit.isPickable = false;

          const satelliteDiameter = Math.max(.16, Math.min(.27,
            baseRadius * (.12 + Math.sqrt(Math.max(0, Number(moon.diameter_km) || 500)) / 500)));
          const satellite = BABYLON.MeshBuilder.CreateSphere(`moon-${index}-${moonIndex}`, {
            diameter: satelliteDiameter, segments: 16
          }, view.scene);
          const satelliteMaterial = new BABYLON.StandardMaterial(`moon-material-${index}-${moonIndex}`, view.scene);
          satelliteMaterial.diffuseColor = new BABYLON.Color3(.72, .76, .82);
          satelliteMaterial.emissiveColor = new BABYLON.Color3(.48, .55, .68);
          satelliteMaterial.specularColor = BABYLON.Color3.Black();
          satellite.material = satelliteMaterial;
          satellite.metadata = { moon, parentWorld: world, moonIndex };
          satellite.isPickable = true;
          makeBodyLabel(moon.name || `Moon ${moonIndex + 1}`, `of ${world.name || world.id}`, satellite, view.scene, `moon-label-${index}-${moonIndex}`, 2.8);
          view.moonEntries.push({
            mesh: satellite, parent: planet, orbitRadius: satelliteOrbitRadius,
            phase: satellitePhase, speed: (Math.PI * 2) / Math.max(30, Number(moon.orbital_period_hours) || 120) * 2.5,
            inclination: (Number(moon.inclination_degrees) || 0) * Math.PI / 180
          });
        });

        if (world.type === 'gas_giant') {
          const band = BABYLON.MeshBuilder.CreateTorus(`world-band-${index}`, {
            diameter: baseRadius * 2.15,
            thickness: Math.max(.035, baseRadius * .12),
            tessellation: 48
          }, view.scene);
          band.parent = planet;
          band.rotation.x = .45;
          band.material = makeMaterial(
            `world-band-material-${index}`,
            new BABYLON.Color3(.84, .66, .45),
            new BABYLON.Color3(.12, .075, .035),
            view.scene
          );
          band.isPickable = false;
        }
      });

      const hoverLabel = byId('systemViewHoverLabel');
      const canvasRect = () => canvas.getBoundingClientRect();
      canvas.addEventListener('pointerleave', () => { hoverLabel?.classList.add('hidden'); view.hoveredWorldId = null; });
      view.scene.onPointerObservable.add(pointerInfo => {
        if (pointerInfo.type === BABYLON.PointerEventTypes.POINTERMOVE) {
          const pick = view.scene.pick(view.scene.pointerX, view.scene.pointerY, mesh => !!(mesh?.metadata?.world || mesh?.metadata?.parentWorld));
          const picked = pick?.hit ? pick.pickedMesh : null;
          const world = picked?.metadata?.world || picked?.metadata?.parentWorld || null;
          if (!hoverLabel) return;
          if (!world) {
            hoverLabel.classList.add('hidden');
            if (view.hoveredWorldId) { const previous = view.planetMeshes.get(view.hoveredWorldId); if (previous?.material) previous.material.emissiveColor = window.CORE_SELECTED_WORLD?.id === view.hoveredWorldId ? new BABYLON.Color3(.22, .19, .06) : new BABYLON.Color3(.025, .035, .045); }
            view.hoveredWorldId = null;
            return;
          }
          const isMoon = !!picked?.metadata?.parentWorld;
          const label = isMoon
            ? `${picked.metadata.moon?.name || `Moon ${Number(picked.metadata.moonIndex) + 1}`} · Moon of ${world.name || world.id} (${world.id})`
            : `${world.name || 'World'} · ${world.id}`;
          hoverLabel.textContent = label;
          hoverLabel.classList.remove('hidden');
          const rect = canvasRect();
          const event = pointerInfo.event;
          hoverLabel.style.left = `${Math.max(8, Math.min(rect.width - 260, event.clientX - rect.left + 14))}px`;
          hoverLabel.style.top = `${Math.max(8, Math.min(rect.height - 48, event.clientY - rect.top + 14))}px`;
          if (view.hoveredWorldId && view.hoveredWorldId !== world.id) { const previous = view.planetMeshes.get(view.hoveredWorldId); if (previous?.material) previous.material.emissiveColor = window.CORE_SELECTED_WORLD?.id === view.hoveredWorldId ? new BABYLON.Color3(.22, .19, .06) : new BABYLON.Color3(.025, .035, .045); }
          view.hoveredWorldId = world.id;
          if (view.planetMeshes.get(world.id)?.material) view.planetMeshes.get(world.id).material.emissiveColor = new BABYLON.Color3(.18, .24, .12);
          return;
        }
        if (pointerInfo.type !== BABYLON.PointerEventTypes.POINTERPICK) return;
        const picked = pointerInfo.pickInfo?.pickedMesh;
        const world = picked?.metadata?.world || picked?.metadata?.parentWorld;
        if (world) selectWorld(world);
      });

      const started = performance.now();
      view.engine.runRenderLoop(() => {
        if (!view.scene || view.scene.isDisposed) return;
        const elapsed = (performance.now() - started) / 1000;
        planetEntries.forEach(entry => {
          const angle = entry.phase + elapsed * entry.speed;
          entry.mesh.position.x = Math.cos(angle) * entry.radius;
          entry.mesh.position.z = Math.sin(angle) * entry.radius;
          entry.mesh.rotation.y += .0025;
        });
        view.moonEntries.forEach(entry => {
          const angle = entry.phase + elapsed * entry.speed;
          entry.mesh.position.set(
            entry.parent.position.x + Math.cos(angle) * entry.orbitRadius,
            Math.sin(angle) * Math.sin(entry.inclination) * entry.orbitRadius,
            entry.parent.position.z + Math.sin(angle) * Math.cos(entry.inclination) * entry.orbitRadius
          );
        });
        star.rotation.y += .0008;
        view.scene.render();
      });

      view.resizeHandler = () => view.engine?.resize();
      window.addEventListener('resize', view.resizeHandler);
      if ('ResizeObserver' in window) {
        view.observer = new ResizeObserver(() => view.engine?.resize());
        view.observer.observe(canvas.parentElement);
      }
      requestAnimationFrame(() => view.engine?.resize());

      byId('systemViewLegend').textContent = worlds.length
        ? `${worlds.length} generated worlds · Shared coplanar orbital layout · Select a planet for its UWP profile · Drag to rotate · Right-drag to pan · Scroll to zoom.`
        : 'No generated worlds are recorded for this system.';

      if (worlds.length) selectWorld(worlds[0]);
      else {
        byId('systemWorldName').textContent = 'No worlds generated';
        byId('systemWorldDescription').textContent = 'This system has no generated planetary bodies.';
        ['systemWorldType','systemWorldSystemSeed','systemWorldSeed','systemWorldUwp','systemWorldStarport','systemWorldSize','systemWorldAtmosphere','systemWorldHydrographics','systemWorldPopulation','systemWorldTech']
          .forEach(id => { byId(id).textContent = '—'; });
      }
    } catch (error) {
      clear();
      byId('systemViewFallback').classList.remove('hidden');
      byId('systemViewFallback').textContent = `3D system view could not initialize: ${error.message || error}`;
    }
  }

  window.CORE_SYSTEM_VIEW = { open, close: clear, select: selectWorld, get activeSystemId() { return view.activeSystemId; } };
  window.addEventListener('pagehide', clear, { once: true });
})();
