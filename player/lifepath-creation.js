(() => {
  const config = window.CORE_LIFEPATH_CONFIG || {};
  const form = document.getElementById('characterCreationForm');
  const speciesSelect = document.getElementById('characterSpecies');
  const snapshotInput = document.getElementById('backgroundSnapshot');
  const timeline = document.getElementById('lifepathTimeline');
  const ageNode = document.getElementById('lifepathAge');
  const speciesNode = document.getElementById('lifepathSpecies');
  const stateNode = document.getElementById('lifepathState');
  const explanation = document.getElementById('lifepathExplanation');
  const regenerateButton = document.getElementById('lifepathRegenerate');
  const nextButton = document.getElementById('lifepathNext');
  const lockButton = document.getElementById('lifepathLock');
  const lockStatus = document.getElementById('lifepathLockStatus');
  const milestone = document.getElementById('lifepathCareerMilestone');
  const saveButton = document.getElementById('createCharacterButton');
  if (!form || !speciesSelect || !snapshotInput || !timeline || !config.endpoint) return;

  let current = null;
  let careerPath = null;
  let locked = false;
  let busy = false;
  const paths = {
    education: 'Formal Education',
    military: 'Military Service',
    employment: 'Employment',
    independent: 'Remain Independent'
  };

  function setLocked(value) {
    locked = value;
    window.CORE_LIFEPATH_LOCKED = locked;
    regenerateButton.disabled = locked || busy;
    nextButton.disabled = locked || busy || !current || current.at_maximum_age || (current.current_age >= current.race.graduation_age && !careerPath);
    lockButton.disabled = busy || !current || (current.current_age >= current.race.graduation_age && !careerPath);
    lockButton.textContent = locked ? 'Unlock to make changes' : 'Lock this history';
    lockStatus.textContent = locked
      ? 'History locked. It will be saved with this character.'
      : 'A generated history must be locked before the character can be saved.';
    snapshotInput.value = locked ? JSON.stringify(buildSnapshot()) : '';
    if (saveButton) saveButton.disabled = locked !== true || !!document.getElementById('abilityValidation')?.classList.contains('is-invalid');
  }

  function buildSnapshot() {
    return {
      locked: true,
      species_key: speciesSelect.value,
      race: current.race,
      seed: current.seed,
      age: current.current_age,
      maturation_age: current.maturation_age,
      graduation_age: current.race.graduation_age,
      max_allowable_age: current.max_allowable_age,
      career_path: careerPath,
      siblings: current.siblings || [],
      history: current.history || []
    };
  }

  function render() {
    if (!current) return;
    ageNode.textContent = `Age: ${current.current_age}`;
    speciesNode.textContent = `Species: ${current.race.name}`;
    stateNode.textContent = current.at_maximum_age ? 'Maximum age reached' : (careerPath ? paths[careerPath.id] : 'History in progress');
    timeline.replaceChildren();
    (current.history || []).forEach(entry => {
      const article = document.createElement('article');
      article.className = 'lifepath-event';
      const meta = document.createElement('div');
      meta.className = 'lifepath-event-meta';
      meta.textContent = `Age ${entry.age} · ${String(entry.type || 'event').replaceAll('_', ' ')}`;
      const heading = document.createElement('h4');
      heading.textContent = entry.title || 'Life event';
      const description = document.createElement('p');
      description.textContent = String(entry.description || '').replace(/<br\s*\/?>/gi, '\n').replace(/<[^>]*>/g, '');
      article.append(meta, heading, description);
      timeline.append(article);
    });
    const atMilestone = current.current_age >= current.race.graduation_age;
    milestone.hidden = !atMilestone || !!careerPath;
    if (careerPath) {
      const note = document.createElement('p');
      note.className = 'lifepath-selected-path';
      note.textContent = `Selected direction: ${paths[careerPath.id]} (age ${careerPath.selected_at_age}).`;
      timeline.append(note);
    }
    if (current.at_maximum_age) explanation.textContent = `The character has reached the species maximum age of ${current.max_allowable_age}. The history can now be locked.`;
    else if (atMilestone && !careerPath) explanation.textContent = 'Choose a profession or independent direction before continuing or locking this history.';
    else explanation.textContent = 'You can regenerate the entire procedural history or add another year. The same seed reproduces the same generated history.';
    setLocked(locked);
  }

  async function request(action) {
    if (busy || locked) return;
    busy = true;
    regenerateButton.disabled = true;
    nextButton.disabled = true;
    lockButton.disabled = true;
    stateNode.textContent = 'Generating…';
    try {
      const body = new URLSearchParams();
      body.set('csrf', config.csrf || '');
      body.set('action', action);
      body.set('race', speciesSelect.value);
      body.set('seed', action === 'advance' && current ? current.seed : '');
      body.set('age', action === 'advance' && current ? String(current.current_age) : '0');
      const response = await fetch(config.endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
        body: body.toString()
      });
      const payload = await response.json();
      if (!response.ok || !payload.ok) throw new Error(payload.error || 'Lifepath generation failed.');
      current = payload.lifepath;
      if (action !== 'advance') careerPath = null;
      render();
    } catch (error) {
      stateNode.textContent = 'Generation failed';
      explanation.textContent = error.message || 'Unable to generate this history.';
    } finally {
      busy = false;
      setLocked(locked);
    }
  }

  regenerateButton.addEventListener('click', () => {
    if (current && !window.confirm('Generate a completely new procedural history? The current unsaved history will be replaced.')) return;
    request('generate');
  });
  nextButton.addEventListener('click', () => request('advance'));
  lockButton.addEventListener('click', () => {
    if (locked) {
      setLocked(false);
      return;
    }
    if (!current) return;
    if (current.current_age >= current.race.graduation_age && !careerPath) return;
    setLocked(true);
    stateNode.textContent = 'History locked';
  });
  milestone.querySelectorAll('[data-lifepath-path]').forEach(button => {
    button.addEventListener('click', () => {
      if (locked || !current || careerPath) return;
      const id = button.dataset.lifepathPath;
      if (!Object.prototype.hasOwnProperty.call(paths, id)) return;
      careerPath = {id, label: paths[id], selected_at_age: current.current_age};
      render();
    });
  });
  speciesSelect.addEventListener('change', () => {
    if (locked) setLocked(false);
    careerPath = null;
    request('generate');
  });
  form.addEventListener('submit', event => {
    if (!locked || !snapshotInput.value) {
      event.preventDefault();
      document.querySelector('[data-creation-tab="background"]')?.click();
      lockStatus.textContent = 'Generate and lock the character history before saving.';
    }
  });

  window.CORE_LIFEPATH_LOCKED = false;
  setLocked(false);
  request('generate');
})();
