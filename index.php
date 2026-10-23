<?php
declare(strict_types=1);

/**
 * CORE browser landing page.
 * API requests are routed to public/index.php by the root .htaccess file.
 */
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CORE — Universe Workbench</title>
    <style>
        :root { color-scheme: dark; --bg:#0b1020; --panel:#141d32; --line:#2b3a57; --text:#edf3ff; --muted:#a9b8d2; --accent:#70d6c2; }
        * { box-sizing: border-box; }
        body { margin:0; font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif; background:radial-gradient(ellipse at top,#172744 0,#0b1020 60%); color:var(--text); min-height:100vh; }
        main { width:min(100% - 32px,1000px); margin:0 auto; padding:48px 0 64px; }
        .eyebrow { color:var(--accent); text-transform:uppercase; letter-spacing:.16em; font-size:.78rem; font-weight:700; }
        h1 { font-size:clamp(2.5rem,7vw,4.5rem); line-height:1; margin:.5rem 0 1rem; letter-spacing:-.04em; }
        .lead { color:var(--muted); max-width:680px; font-size:1.1rem; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr)); gap:16px; margin-top:28px; }
        section { background:rgba(20,29,50,.92); border:1px solid var(--line); border-radius:14px; padding:22px; min-width:0; }
        h2 { font-size:1.1rem; margin:0 0 12px; }
        label { display:block; color:var(--muted); font-size:.9rem; margin-bottom:6px; }
        input,button,textarea { font:inherit; min-height:44px; border-radius:8px; }
        input,textarea { width:100%; padding:9px 12px; color:var(--text); background:#0b1325; border:1px solid var(--line); }
        textarea { min-height:110px; resize:vertical; }
        button { padding:9px 14px; background:var(--accent); color:#08201d; font-weight:700; border:0; cursor:pointer; margin-top:10px; }
        button.secondary { background:#263752; color:var(--text); border:1px solid #3a4d6e; }
        .actions { display:flex; flex-wrap:wrap; gap:8px; }
        pre { white-space:pre-wrap; overflow-wrap:anywhere; background:#080d18; padding:14px; border-radius:8px; border:1px solid var(--line); color:#d7e5ff; max-height:420px; overflow:auto; }
        .status { color:var(--muted); min-height:1.5em; margin-top:8px; }
        ul { color:var(--muted); padding-left:20px; }
        code { color:#b8f4e7; }
        footer { color:var(--muted); font-size:.85rem; margin-top:24px; }
    </style>
</head>
<body>
<main>
    <div class="eyebrow">Procedural sci-fi sandbox · Foundation 0.1.15</div>
    <h1>CORE</h1>
    <p class="lead">The development workbench for a seed-driven universe. Check the server, explore generated universes, and run isolated automated QA directly from this interface.</p>

    <div class="grid">
        <section>
            <h2>Server status</h2>
            <p>Check that the PHP application and API are responding.</p>
            <div class="actions"><button id="healthButton" type="button">Check server</button></div>
            <p id="healthStatus" class="status" aria-live="polite">Not checked yet.</p>
        </section>
        <section>
            <h2>Universe seed</h2>
            <label for="seed">Seed phrase</label>
            <input id="seed" name="seed" value="first-universe" maxlength="120" autocomplete="off">
            <div class="actions">
                <button id="previewButton" type="button">Preview universe</button>
                <button id="createButton" class="secondary" type="button">Create / load</button>
            </div>
            <p id="universeStatus" class="status" aria-live="polite">Preview does not save. Create / load writes a JSON record.</p>
        </section>
        <section style="grid-column:1/-1">
            <h2>Universe state API (development only)</h2>
            <p>Append a versioned change event to a saved universe. This modifies saved state; use a test universe while experimenting. Stale revisions are rejected. This endpoint is restricted to development mode and local/private-network clients.</p>
            <label for="stateUniverseId">Universe ID</label>
            <input id="stateUniverseId" autocomplete="off" placeholder="Filled after Create / Load">
            <label for="stateRevision">Expected revision</label>
            <input id="stateRevision" type="number" min="0" step="1" value="0">
            <label for="stateChange">Change JSON (must include a non-empty string type)</label>
            <textarea id="stateChange" spellcheck="false">{
  "type": "note_added",
  "value": "Workbench test event"
}</textarea>
            <div class="actions"><button id="stateUpdateButton" type="button">Append state change</button><button id="explorationExampleButton" class="secondary" type="button">Use first system as exploration event</button></div>
            <p id="stateStatus" class="status" aria-live="polite">No state update submitted.</p>
        </section>
        <section style="grid-column:1/-1">
            <h2>Player characters (development only)</h2>
            <p>Create a basic character record linked to the currently selected universe. This is a prototype registry, not an account-owned character system; do not expose it publicly.</p>
            <label for="characterUniverseId">Universe ID</label>
            <input id="characterUniverseId" autocomplete="off" placeholder="Filled after Create / Load">
            <label for="characterName">Character name</label>
            <input id="characterName" maxlength="40" autocomplete="off" placeholder="e.g. Mira Voss">
            <label for="characterRole">Role / starting concept</label>
            <input id="characterRole" maxlength="40" value="Explorer" autocomplete="off">
            <div class="actions"><button id="createCharacterButton" type="button">Create character</button><button id="refreshCharactersButton" class="secondary" type="button">Refresh character list</button></div>
            <p id="characterStatus" class="status" aria-live="polite">Select a universe before creating a character.</p>
            <h3>Character location</h3>
            <label for="characterSelect">Character</label>
            <select id="characterSelect" style="width:100%;padding:9px 12px;min-height:44px;border-radius:8px;color:var(--text);background:#0b1325;border:1px solid var(--line)"><option value="">Load characters first</option></select>
            <label for="characterSystemSelect">Destination system</label>
            <select id="characterSystemSelect" style="width:100%;padding:9px 12px;min-height:44px;border-radius:8px;color:var(--text);background:#0b1325;border:1px solid var(--line)"><option value="">Create / load a universe first</option></select>
            <label for="characterRevision">Expected character revision</label>
            <input id="characterRevision" type="number" min="0" step="1" value="0">
            <div class="actions"><button id="moveCharacterButton" type="button">Move character</button></div>
            <p id="characterLocationStatus" class="status" aria-live="polite">New characters start in the universe's first system. Choose another system to move them.</p>
            <pre id="characterOutput" aria-live="polite">No characters loaded.</pre>
        </section>
        <section style="grid-column:1/-1">
            <h2>Response</h2>
            <pre id="output" aria-live="polite">Ready. Choose an action above.</pre>
        </section>
        <section style="grid-column:1/-1">
            <h2>Workbench activity log</h2>
            <p>Browser-side actions and failures appear here. Server-side PHP errors are written to <code>storage/logs/core.log</code>.</p>
            <pre id="activityLog" aria-live="polite">Workbench initialized.</pre>
            <div class="actions"><button id="clearLogButton" class="secondary" type="button">Clear activity log</button></div>
        </section>
        <section style="grid-column:1/-1">
            <h2>Automated QA</h2>
            <p>Run safe, isolated tests from the browser. Persistence tests use temporary data and do not modify your saved universes.</p>
            <div class="actions"><button id="qaButton" type="button">Run automated tests</button></div>
            <p id="qaStatus" class="status" aria-live="polite">Not run yet.</p>
            <pre id="qaOutput" aria-live="polite">Ready to run QA.</pre>
        </section>
        <section style="grid-column:1/-1">
            <h2>Current foundation</h2>
            <ul>
                <li>PHP request routing and JSON responses</li>
                <li>Deterministic seed-based universe preview</li>
                <li>JSON file persistence for created universes</li>
                <li>Structured application logging</li>
            </ul>
            <p class="status">This is an early development scaffold, not a finished game. Accounts, character ownership, gameplay progression, and access control are not implemented yet.</p>
        </section>
    </div>
    <footer>CORE · Local development workbench · Do not expose publicly until authentication and access controls are implemented.</footer>
</main>
<script>
(() => {
    const output = document.getElementById('output');
    const healthStatus = document.getElementById('healthStatus');
    const universeStatus = document.getElementById('universeStatus');
    const seedInput = document.getElementById('seed');
    let currentUniverseBaseline = null;
    const activityLog = document.getElementById('activityLog');
    let loadedCharacters = [];
    function populateSystemChoices() {
        const select = document.getElementById('characterSystemSelect');
        const systems = currentUniverseBaseline?.systems || [];
        select.replaceChildren();
        if (!systems.length) {
            const option = document.createElement('option'); option.value = ''; option.textContent = 'Create / load a universe first'; select.append(option); return;
        }
        for (const system of systems) {
            const option = document.createElement('option');
            option.value = system.id;
            option.textContent = system.name + ' — ' + system.id + ' (' + system.classification + ')';
            select.append(option);
        }
    }
    function populateCharacterChoices() {
        const select = document.getElementById('characterSelect');
        const prior = select.value;
        select.replaceChildren();
        if (!loadedCharacters.length) {
            const option = document.createElement('option'); option.value = ''; option.textContent = 'No characters in this universe'; select.append(option); return;
        }
        for (const character of loadedCharacters) {
            const option = document.createElement('option'); option.value = character.id;
            option.textContent = character.name + ' — ' + character.id;
            option.dataset.revision = String(character.revision ?? 0);
            select.append(option);
        }
        if (loadedCharacters.some(character => character.id === prior)) select.value = prior;
        document.getElementById('characterRevision').value = select.selectedOptions[0]?.dataset.revision ?? '0';
    }
    document.getElementById('characterSelect').addEventListener('change', () => {
        document.getElementById('characterRevision').value = document.getElementById('characterSelect').selectedOptions[0]?.dataset.revision ?? '0';
    });
    function logActivity(level, message, details = '') {
        const stamp = new Date().toLocaleTimeString();
        const line = '[' + stamp + '] ' + level.toUpperCase() + ' — ' + message + (details ? ' — ' + details : '');
        activityLog.textContent += '\n' + line;
        activityLog.scrollTop = activityLog.scrollHeight;
        if (level === 'error') console.error(line); else console.info(line);
    }
    document.getElementById('clearLogButton').addEventListener('click', () => { activityLog.textContent = 'Activity log cleared.'; });
    window.addEventListener('error', event => logActivity('error', 'Browser error', event.message + ' (' + event.filename + ':' + event.lineno + ')'));
    window.addEventListener('unhandledrejection', event => logActivity('error', 'Unhandled browser promise rejection', String(event.reason)));
    logActivity('info', 'Workbench ready');

    async function showResponse(response) {
        const text = await response.text();
        logActivity(response.ok ? 'info' : 'error', 'HTTP ' + response.status + ' ' + response.url, response.ok ? 'request completed' : 'request returned an error');
        let data;
        try { data = JSON.parse(text); } catch { data = { raw_response: text }; }
        output.textContent = JSON.stringify(data, null, 2);
        return { response, data };
    }

    document.getElementById('healthButton').addEventListener('click', async () => {
        healthStatus.textContent = 'Checking…';
        try {
            const result = await showResponse(await fetch('api/health.php', { headers: { 'Accept': 'application/json' } }));
            healthStatus.textContent = result.response.ok && result.data.ok ? 'Online — API responded successfully.' : 'The server responded, but reported a problem.';
            if (!result.response.ok || !result.data.ok) logActivity('error', 'Health check failed', JSON.stringify(result.data));
        } catch (error) {
            healthStatus.textContent = 'Request failed. Check Apache/PHP and the browser console.';
            output.textContent = String(error);
            logActivity('error', 'Create/load request threw an exception', String(error));
            logActivity('error', 'Preview request threw an exception', String(error));
            logActivity('error', 'Health request threw an exception', String(error));
        }
    });

    document.getElementById('previewButton').addEventListener('click', async () => {
        const seed = seedInput.value.trim();
        if (!seed) { universeStatus.textContent = 'Enter a seed first.'; return; }
        universeStatus.textContent = 'Generating preview…';
        try {
            const result = await showResponse(await fetch('api/universe-preview.php?seed=' + encodeURIComponent(seed)));
            universeStatus.textContent = result.response.ok ? 'Preview generated. Nothing was saved.' : 'Preview request failed.';
            if (!result.response.ok || !result.data.ok) logActivity('error', 'Universe preview failed', JSON.stringify(result.data));
        } catch (error) {
            universeStatus.textContent = 'Request failed. Check Apache/PHP and the browser console.';
            output.textContent = String(error);
        }
    });

    document.getElementById('qaButton').addEventListener('click', async () => {
        const button = document.getElementById('qaButton');
        const status = document.getElementById('qaStatus');
        const qaOutput = document.getElementById('qaOutput');
        button.disabled = true;
        status.textContent = 'Running isolated tests…';
        qaOutput.textContent = 'Please wait…';
        try {
            const response = await fetch('api/qa.php', { method: 'POST', headers: { 'Accept': 'application/json' } });
            const text = await response.text();
            let data;
            try { data = JSON.parse(text); } catch { data = { ok: false, error: { message: text || 'Invalid server response' } }; }
            logActivity(response.ok && data.ok ? 'info' : 'error', 'Automated QA', (data.passed ?? 0) + ' passed, ' + (data.failed ?? 0) + ' failed');
            qaOutput.textContent = JSON.stringify(data, null, 2);
            status.textContent = data.ok ? 'PASS — ' + data.passed + ' tests passed.' : 'FAIL — ' + (data.passed ?? 0) + ' passed, ' + (data.failed ?? 0) + ' failed. See results below.';
        } catch (error) {
            status.textContent = 'QA request failed.';
            qaOutput.textContent = String(error);
            logActivity('error', 'Automated QA request failed', String(error));
        } finally {
            button.disabled = false;
        }
    });

    document.getElementById('explorationExampleButton').addEventListener('click', () => {
        const firstSystem = currentUniverseBaseline?.systems?.[0];
        if (!firstSystem || !firstSystem.id) {
            document.getElementById('stateStatus').textContent = 'Create / load a universe first so CORE knows its generated systems.';
            logActivity('error', 'Could not prepare exploration event', 'No loaded universe baseline is available');
            return;
        }
        document.getElementById('stateChange').value = JSON.stringify({ type: 'system_explored', system_id: firstSystem.id }, null, 2);
        document.getElementById('stateStatus').textContent = 'Prepared exploration event for ' + firstSystem.name + '. Confirm revision and append when ready.';
        logActivity('info', 'Exploration event prepared', firstSystem.name + ' (' + firstSystem.id + ')');
    });

    document.getElementById('stateUpdateButton').addEventListener('click', async () => {
        const status = document.getElementById('stateStatus');
        const universeId = document.getElementById('stateUniverseId').value.trim();
        const expectedRevision = Number(document.getElementById('stateRevision').value);
        let change;
        if (!universeId) { status.textContent = 'Create / load a universe or enter its ID first.'; return; }
        if (!Number.isSafeInteger(expectedRevision) || expectedRevision < 0) { status.textContent = 'Expected revision must be a non-negative integer.'; return; }
        try { change = JSON.parse(document.getElementById('stateChange').value); }
        catch (error) { status.textContent = 'Change JSON is invalid: ' + error.message; logActivity('error', 'State change JSON parse failed', String(error)); return; }
        if (!change || Array.isArray(change) || typeof change !== 'object') { status.textContent = 'Change must be a JSON object.'; return; }
        const button = document.getElementById('stateUpdateButton');
        button.disabled = true;
        status.textContent = 'Submitting revision-checked change…';
        try {
            const response = await fetch('api/universe-state.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ universe_id: universeId, expected_revision: expectedRevision, change })
            });
            const text = await response.text();
            let data;
            try { data = JSON.parse(text); } catch { data = { ok: false, error: { message: text || 'Invalid server response' } }; }
            output.textContent = JSON.stringify(data, null, 2);
            if (response.ok && data.ok) {
                document.getElementById('stateRevision').value = data.state.revision;
                status.textContent = 'Change saved. Current revision: ' + data.state.revision + '.';
                logActivity('info', 'Universe state updated', universeId + ' → revision ' + data.state.revision);
            } else {
                status.textContent = (data.error?.code || 'request_failed') + ': ' + (data.error?.message || 'State update failed.');
                logActivity('error', 'Universe state update failed', JSON.stringify(data));
            }
        } catch (error) {
            status.textContent = 'Request failed. Check the server and activity log.';
            logActivity('error', 'Universe state request failed', String(error));
        } finally { button.disabled = false; }
    });

    async function refreshCharacters() {
        const universeId = document.getElementById('characterUniverseId').value.trim();
        const status = document.getElementById('characterStatus');
        if (!universeId) { status.textContent = 'Create / load a universe first.'; return; }
        try {
            const response = await fetch('api/characters.php?universe_id=' + encodeURIComponent(universeId), { headers: { 'Accept': 'application/json' } });
            const text = await response.text();
            let data;
            try { data = JSON.parse(text); } catch { data = { ok: false, error: { message: text || 'Invalid server response' } }; }
            document.getElementById('characterOutput').textContent = JSON.stringify(data, null, 2);
            loadedCharacters = response.ok && data.ok ? data.characters : [];
            populateCharacterChoices();
            status.textContent = response.ok && data.ok ? data.characters.length + ' character(s) linked to this universe.' : (data.error?.message || 'Could not load characters.');
            logActivity(response.ok && data.ok ? 'info' : 'error', 'Character list', status.textContent);
        } catch (error) {
            status.textContent = 'Character request failed.';
            document.getElementById('characterOutput').textContent = String(error);
            logActivity('error', 'Character list request failed', String(error));
        }
    }
    document.getElementById('refreshCharactersButton').addEventListener('click', refreshCharacters);
    document.getElementById('moveCharacterButton').addEventListener('click', async () => {
        const universeId = document.getElementById('characterUniverseId').value.trim();
        const characterId = document.getElementById('characterSelect').value;
        const systemId = document.getElementById('characterSystemSelect').value;
        const revision = Number(document.getElementById('characterRevision').value);
        const status = document.getElementById('characterLocationStatus');
        if (!universeId || !characterId || !systemId) { status.textContent = 'Select a universe, character, and destination system first.'; return; }
        if (!Number.isSafeInteger(revision) || revision < 0) { status.textContent = 'Expected character revision must be a non-negative integer.'; return; }
        const button = document.getElementById('moveCharacterButton'); button.disabled = true; status.textContent = 'Updating character location…';
        try {
            const response = await fetch('api/character-location.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ universe_id: universeId, character_id: characterId, system_id: systemId, expected_revision: revision }) });
            const text = await response.text(); let data;
            try { data = JSON.parse(text); } catch { data = { ok: false, error: { message: text || 'Invalid server response' } }; }
            document.getElementById('characterOutput').textContent = JSON.stringify(data, null, 2);
            if (response.ok && data.ok) {
                status.textContent = 'Character is now in ' + systemId + '. Revision: ' + data.character.revision + '.';
                await refreshCharacters();
                document.getElementById('characterSelect').value = characterId;
                document.getElementById('characterRevision').value = String(data.character.revision);
                logActivity('info', 'Character moved', characterId + ' → ' + systemId + ' (revision ' + data.character.revision + ')');
            } else {
                status.textContent = (data.error?.code || 'request_failed') + ': ' + (data.error?.message || 'Character move failed.');
                logActivity('error', 'Character move failed', JSON.stringify(data));
            }
        } catch (error) { status.textContent = 'Character move request failed.'; logActivity('error', 'Character move request failed', String(error)); }
        finally { button.disabled = false; }
    });
    document.getElementById('createCharacterButton').addEventListener('click', async () => {
        const universeId = document.getElementById('characterUniverseId').value.trim();
        const name = document.getElementById('characterName').value.trim();
        const role = document.getElementById('characterRole').value.trim() || 'Explorer';
        const status = document.getElementById('characterStatus');
        if (!universeId) { status.textContent = 'Create / load a universe first.'; return; }
        if (!name) { status.textContent = 'Enter a character name.'; return; }
        const button = document.getElementById('createCharacterButton');
        button.disabled = true;
        status.textContent = 'Creating character…';
        try {
            const response = await fetch('api/characters.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ universe_id: universeId, name, role })
            });
            const text = await response.text();
            let data;
            try { data = JSON.parse(text); } catch { data = { ok: false, error: { message: text || 'Invalid server response' } }; }
            document.getElementById('characterOutput').textContent = JSON.stringify(data, null, 2);
            status.textContent = response.ok && data.ok ? 'Character created: ' + data.character.name + ' (' + data.character.id + ').' : (data.error?.message || 'Character creation failed.');
            logActivity(response.ok && data.ok ? 'info' : 'error', 'Character creation', status.textContent);
            if (response.ok && data.ok) { await refreshCharacters(); document.getElementById('characterSelect').value = data.character.id; document.getElementById('characterRevision').value = String(data.character.revision ?? 0); }
        } catch (error) {
            status.textContent = 'Character request failed.';
            document.getElementById('characterOutput').textContent = String(error);
            logActivity('error', 'Character creation request failed', String(error));
        } finally { button.disabled = false; }
    });

    document.getElementById('createButton').addEventListener('click', async () => {
        const seed = seedInput.value.trim();
        if (!seed) { universeStatus.textContent = 'Enter a seed first.'; return; }
        universeStatus.textContent = 'Creating or loading universe…';
        try {
            const result = await showResponse(await fetch('api/universe.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ seed })
            }));
            universeStatus.textContent = result.response.ok ? 'Universe created or loaded.' : 'Create/load request failed.';
            if (result.response.ok && result.data.universe) {
                currentUniverseBaseline = result.data.universe.baseline || null;
                document.getElementById('stateUniverseId').value = result.data.universe.id || '';
                document.getElementById('characterUniverseId').value = result.data.universe.id || '';
                document.getElementById('characterStatus').textContent = 'Universe selected. You can create or list its characters.';
                document.getElementById('stateRevision').value = result.data.universe.state?.revision ?? 0;
                document.getElementById('stateStatus').textContent = 'Universe selected. Confirm the ID and expected revision before updating.';
                populateSystemChoices();
                await refreshCharacters();
            }
            if (!result.response.ok || !result.data.ok) logActivity('error', 'Create/load failed', JSON.stringify(result.data));
        } catch (error) {
            universeStatus.textContent = 'Request failed. Check Apache/PHP and the browser console.';
            output.textContent = String(error);
        }
    });
})();
</script>
</body>
</html>
