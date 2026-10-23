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
    <div class="eyebrow">Procedural sci-fi sandbox · Foundation 0.1.10</div>
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
            <div class="actions"><button id="stateUpdateButton" type="button">Append state change</button></div>
            <p id="stateStatus" class="status" aria-live="polite">No state update submitted.</p>
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
            <p class="status">This is an early development scaffold, not a finished game. Accounts, characters, gameplay systems, and access control are not implemented yet.</p>
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
    const activityLog = document.getElementById('activityLog');
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
                document.getElementById('stateUniverseId').value = result.data.universe.id || '';
                document.getElementById('stateRevision').value = result.data.universe.state?.revision ?? 0;
                document.getElementById('stateStatus').textContent = 'Universe selected. Confirm the ID and expected revision before updating.';
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
