(() => {
  'use strict';
  const form = document.getElementById('characterCreationForm');
  const rulesNode = document.getElementById('characterCreationRules');
  if (!form || !rulesNode) return;

  let rules;
  try { rules = JSON.parse(rulesNode.textContent || '{}'); } catch (_) { return; }
  const generation = rules.ability_generation || {};
  const costs = generation.costs || {};
  const budget = Number(generation.points || 0);
  const speciesSelect = document.getElementById('characterSpecies');
  const roleSelect = document.getElementById('characterRole');
  const speciesDescription = document.getElementById('speciesDescription');
  const speciesAdjustmentDescription = document.getElementById('speciesAdjustmentDescription');
  const roleDescription = document.getElementById('roleDescription');

  // The section navigation changes the visible workspace only; all configured
  // form values remain in the same form and are submitted together.
  const tabButtons = Array.from(document.querySelectorAll('[data-creation-tab]'));
  const tabPanels = Array.from(document.querySelectorAll('[data-creation-panel]'));
  function activateTab(name, moveFocus = false) {
    tabButtons.forEach(button => {
      const active = button.dataset.creationTab === name;
      button.setAttribute('aria-selected', active ? 'true' : 'false');
      button.classList.toggle('is-active', active);
      button.tabIndex = active ? 0 : -1;
      if (active && moveFocus) button.focus();
    });
    tabPanels.forEach(panel => { panel.hidden = panel.dataset.creationPanel !== name; });
  }
  tabButtons.forEach((button, index) => {
    button.addEventListener('click', () => activateTab(button.dataset.creationTab));
    button.addEventListener('keydown', event => {
      if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
      event.preventDefault();
      let next = index;
      if (event.key === 'ArrowRight') next = (index + 1) % tabButtons.length;
      if (event.key === 'ArrowLeft') next = (index - 1 + tabButtons.length) % tabButtons.length;
      if (event.key === 'Home') next = 0;
      if (event.key === 'End') next = tabButtons.length - 1;
      activateTab(tabButtons[next].dataset.creationTab, true);
    });
  });
  activateTab('appearance');

  function updateDescriptions() {
    const species = getSpecies();
    if (speciesDescription) speciesDescription.textContent = species?.description || 'No species description has been configured.';
    if (speciesAdjustmentDescription) speciesAdjustmentDescription.textContent = species?.ability_adjustment_description || 'No additional species adjustments are currently configured.';
    const role = (rules.roles || []).find(item => item.id === roleSelect?.value);
    if (roleDescription) roleDescription.textContent = role?.description || 'No role description has been configured.';
  }

  const humanBonusSelect = document.getElementById('humanBonusAttribute');
  const humanBonusRow = document.getElementById('humanBonusRow');
  const remainingNode = document.getElementById('abilityPointsRemaining');
  const spentNode = document.getElementById('abilityPointsSpent');
  const summaryNode = document.getElementById('speciesAdjustmentSummary');
  const validationNode = document.getElementById('abilityValidation');
  const submitButton = document.getElementById('createCharacterButton');
  const previewColor = document.getElementById('previewColor');
  const previewSaturation = document.getElementById('previewSaturation');
  const previewBrightness = document.getElementById('previewBrightness');
  const tintValue = document.getElementById('characterTintValue');
  const saturationValue = document.getElementById('characterSaturationValue');
  const brightnessValue = document.getElementById('characterBrightnessValue');
  const colorValue = document.getElementById('appearanceColorValue');
  const saturationOutput = document.getElementById('saturationOutput');
  const brightnessOutput = document.getElementById('brightnessOutput');
  const abilityInputs = Array.from(form.querySelectorAll('[data-ability]'));
  function syncAppearanceInputs() {
    if (previewColor && tintValue) tintValue.value = previewColor.value.toLowerCase();
    if (previewSaturation && saturationValue) saturationValue.value = previewSaturation.value;
    if (previewBrightness && brightnessValue) brightnessValue.value = previewBrightness.value;
    if (colorValue && previewColor) colorValue.textContent = previewColor.value.toUpperCase();
    if (saturationOutput && previewSaturation) saturationOutput.textContent = `${previewSaturation.value}%`;
    if (brightnessOutput && previewBrightness) brightnessOutput.textContent = `${previewBrightness.value}%`;
  }
  [previewColor, previewSaturation, previewBrightness].filter(Boolean).forEach(input => input.addEventListener('input', syncAppearanceInputs));
  document.querySelectorAll('[data-appearance-preset]').forEach(button => {
    button.addEventListener('click', () => {
      if (previewColor) previewColor.value = button.dataset.appearancePreset;
      syncAppearanceInputs();
      previewColor?.dispatchEvent(new Event('input', { bubbles: true }));
    });
  });
  document.getElementById('resetAppearanceButton')?.addEventListener('click', () => {
    if (previewColor) previewColor.value = '#61d8e8';
    if (previewSaturation) previewSaturation.value = '100';
    if (previewBrightness) previewBrightness.value = '100';
    syncAppearanceInputs();
    previewColor?.dispatchEvent(new Event('input', { bubbles: true }));
    previewSaturation?.dispatchEvent(new Event('input', { bubbles: true }));
    previewBrightness?.dispatchEvent(new Event('input', { bubbles: true }));
  });
  syncAppearanceInputs();
  const attributeLabels = Object.fromEntries((rules.attributes || []).map(item => [item.id, item.label || item.id]));

  const signed = value => value > 0 ? `+${value}` : String(value);
  const getSpecies = () => (rules.species || []).find(item => item.id === speciesSelect.value) || null;

  function update() {
    const species = getSpecies();
    const configured = species && species.ability_adjustments && typeof species.ability_adjustments === 'object' ? species.ability_adjustments : {};
    const flexible = Object.prototype.hasOwnProperty.call(configured, 'choice');
    const adjustments = {};
    if (flexible && humanBonusSelect.value) adjustments[humanBonusSelect.value] = Number(configured.choice || 0);
    else Object.entries(configured).forEach(([id, value]) => { if (id !== 'choice') adjustments[id] = Number(value || 0); });

    let spent = 0;
    abilityInputs.forEach(input => {
      const score = Number(input.value);
      spent += Number(costs[String(score)] ?? 0);
      const id = input.dataset.ability;
      const adjustment = adjustments[id] || 0;
      const finalScore = score + adjustment;
      const finalNode = form.querySelector(`[data-final-score="${CSS.escape(id)}"]`);
      const adjustmentNode = form.querySelector(`[data-ability-adjustment="${CSS.escape(id)}"]`);
      if (finalNode) finalNode.textContent = String(finalScore);
      if (adjustmentNode) adjustmentNode.textContent = adjustment === 0 ? 'No species adjustment' : `Species: ${signed(adjustment)}`;
    });

    const remaining = budget - spent;
    if (remainingNode) remainingNode.textContent = String(remaining);
    if (spentNode) spentNode.textContent = `${spent} / ${budget}`;
    if (humanBonusRow) humanBonusRow.hidden = !flexible;
    if (humanBonusSelect) humanBonusSelect.disabled = !flexible;
    if (summaryNode) {
      const entries = Object.entries(adjustments).filter(([, value]) => value !== 0);
      summaryNode.textContent = entries.length ? entries.map(([id, value]) => `${signed(value)} ${attributeLabels[id] || id}`).join(', ') : 'No species adjustments';
    }

    updateDescriptions();
    const valid = spent <= budget;
    if (validationNode) {
      validationNode.textContent = valid
        ? (remaining === 0 ? 'Point-buy budget fully allocated.' : `${remaining} point(s) remaining. You may save with points unspent.`)
        : `Over budget by ${spent - budget} point(s). Reduce one or more ability scores.`;
      validationNode.classList.toggle('is-invalid', !valid);
      validationNode.classList.toggle('is-valid', valid);
    }
    if (submitButton) submitButton.disabled = !valid || window.CORE_LIFEPATH_LOCKED !== true;
    return valid;
  }

  abilityInputs.forEach(input => input.addEventListener('change', update));
  speciesSelect.addEventListener('change', update);
  if (roleSelect) roleSelect.addEventListener('change', updateDescriptions);
  humanBonusSelect.addEventListener('change', update);
  form.addEventListener('submit', event => { if (!update()) event.preventDefault(); });
  update();
})();
