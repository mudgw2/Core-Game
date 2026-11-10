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
  const humanBonusSelect = document.getElementById('humanBonusAttribute');
  const humanBonusRow = document.getElementById('humanBonusRow');
  const remainingNode = document.getElementById('abilityPointsRemaining');
  const spentNode = document.getElementById('abilityPointsSpent');
  const summaryNode = document.getElementById('speciesAdjustmentSummary');
  const validationNode = document.getElementById('abilityValidation');
  const submitButton = document.getElementById('createCharacterButton');
  const abilityInputs = Array.from(form.querySelectorAll('[data-ability]'));
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

    const valid = spent <= budget;
    if (validationNode) {
      validationNode.textContent = valid
        ? (remaining === 0 ? 'Point-buy budget fully allocated.' : `${remaining} point(s) remaining. You may save with points unspent.`)
        : `Over budget by ${spent - budget} point(s). Reduce one or more ability scores.`;
      validationNode.classList.toggle('is-invalid', !valid);
      validationNode.classList.toggle('is-valid', valid);
    }
    if (submitButton) submitButton.disabled = !valid;
    return valid;
  }

  abilityInputs.forEach(input => input.addEventListener('change', update));
  speciesSelect.addEventListener('change', update);
  humanBonusSelect.addEventListener('change', update);
  form.addEventListener('submit', event => { if (!update()) event.preventDefault(); });
  update();
})();
