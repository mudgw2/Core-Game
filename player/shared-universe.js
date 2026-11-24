(() => {
  'use strict';
  const form = document.getElementById('sharedUniverseVisitForm');
  const status = document.getElementById('sharedUniverseVisitStatus');
  const csrf = document.body.dataset.csrfToken || '';
  const apiRoot = document.body.dataset.apiRoot || '';

  async function post(path, payload) {
    const response = await fetch(`${apiRoot}/api/${path}`, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CORE-CSRF': csrf },
      body: JSON.stringify(payload)
    });
    const contentType = response.headers.get('content-type') || '';
    if (!contentType.toLowerCase().includes('application/json')) {
      // A redirect, Apache 404 page, or PHP error page must not surface as a JSON parse exception.
      throw new Error(`CORE API returned a non-JSON response (HTTP ${response.status}). The API route may not be reaching the application router.`);
    }
    let data;
    try {
      data = await response.json();
    } catch (_) {
      throw new Error(`CORE API returned invalid JSON (HTTP ${response.status}).`);
    }
    if (!response.ok || data.ok === false) throw new Error(data.error?.message || `Request failed (${response.status}).`);
    return data;
  }

  form?.addEventListener('submit', async event => {
    event.preventDefault();
    status.textContent = 'Validating share code…';
    try {
      await post('universe/visit', {
        character_id: document.getElementById('sharedVisitCharacter').value,
        share_code: document.getElementById('sharedUniverseCode').value.trim()
      });
      status.textContent = 'Joined shared universe. Reloading character roster…';
      window.location.reload();
    } catch (error) { status.textContent = error.message; }
  });

  document.querySelectorAll('.shared-universe-share').forEach(button => button.addEventListener('click', async () => {
    const output = button.parentElement.querySelector('.shared-universe-code');
    button.disabled = true;
    output.textContent = 'Generating code…';
    try {
      const data = await post('universe-share.php', { universe_id: button.dataset.universeId });
      output.textContent = `Share code: ${data.share_code} — copy it now. Rotating the code invalidates the previous one.`;
    } catch (error) { output.textContent = error.message; }
    finally { button.disabled = false; }
  }));

  document.querySelectorAll('.shared-universe-leave').forEach(button => button.addEventListener('click', async () => {
    button.disabled = true;
    try { await post('universe/leave', { character_id: button.dataset.characterId }); window.location.reload(); }
    catch (error) { window.alert(error.message); button.disabled = false; }
  }));
})();
