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
        input,button { font:inherit; min-height:44px; border-radius:8px; }
        input { width:100%; padding:9px 12px; color:var(--text); background:#0b1325; border:1px solid var(--line); }
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
    <div class="eyebrow">Procedural sci-fi sandbox · Foundation 0.1.5</div>
    <h1>CORE</h1>
    <p class="lead">The development workbench for a seed-driven universe. This is the first browser page for the PHP foundation; use it to check the server and preview or create a universe.</p>

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
