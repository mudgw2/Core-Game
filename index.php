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
        input,textarea,select { width:100%; padding:9px 12px; color:var(--text); background:#0b1325; border:1px solid #526585; color-scheme:dark; }
        select { min-height:48px; border-width:2px; }
        select:focus-visible,input:focus-visible,textarea:focus-visible,button:focus-visible { outline:3px solid #70d6c2; outline-offset:2px; }
        select option { color:#edf3ff; background:#0b1325; }
        textarea { min-height:110px; resize:vertical; }
        .field-help { color:#f1f5ff; background:#202e48; border-left:4px solid var(--accent); padding:10px 12px; border-radius:6px; margin:8px 0 14px; }
        .field-help strong { color:#ffffff; }
        button { padding:9px 14px; background:var(--accent); color:#08201d; font-weight:700; border:0; cursor:pointer; margin-top:10px; }
        button.secondary { background:#263752; color:var(--text); border:1px solid #3a4d6e; }
        .actions { display:flex; flex-wrap:wrap; gap:8px; }
        pre { white-space:pre-wrap; overflow-wrap:anywhere; background:#080d18; padding:14px; border-radius:8px; border:1px solid var(--line); color:#d7e5ff; max-height:420px; overflow:auto; }
        .status { color:var(--muted); min-height:1.5em; margin-top:8px; }
        ul { color:var(--muted); padding-left:20px; }
        code { color:#b8f4e7; }
        footer { color:var(--muted); font-size:.85rem; margin-top:24px; }
        .dashboard-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,190px),1fr)); gap:10px; margin:14px 0; }
        .dashboard-tile { background:#0b1325; border:1px solid #526585; border-radius:10px; padding:14px; min-width:0; }
        .dashboard-label { display:block; color:#b9c9e4; font-size:.82rem; margin-bottom:5px; }
        .dashboard-tile strong { display:block; overflow-wrap:anywhere; font-size:1.05rem; }
        .dashboard-detail { display:block; color:#c3d0e5; font-size:.9rem; margin-top:4px; overflow-wrap:anywhere; }
        .dashboard-lists { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr)); gap:12px; }
        .dashboard-lists > div { background:#101a2e; border:1px solid var(--line); border-radius:8px; padding:12px; min-width:0; }
        .dashboard-lists h4 { margin:0 0 6px; font-size:.95rem; }
        .dashboard-list { margin:0; color:#d5e2f7; overflow-wrap:anywhere; }

        .app-header { padding-top:38px; }
        .app-header h1 { font-size:clamp(2.5rem,7vw,4.2rem); line-height:1; margin:.5rem 0 1rem; letter-spacing:-.04em; }
        .global-menu { display:flex; flex-wrap:wrap; gap:8px; padding:10px; margin:24px 0 0; border:1px solid #334564; border-radius:14px; background:#0d1628; }
        .global-menu button { margin:0; min-height:46px; background:transparent; color:#d8e4f7; border:1px solid transparent; }
        .global-menu button[aria-selected="true"] { background:var(--accent); color:#08201d; border-color:var(--accent); }
        .global-menu button:hover { border-color:#7e94b7; }
        .global-menu button:focus-visible { outline:3px solid #fff; outline-offset:2px; }
        .tab-summary { display:flex; align-items:center; flex-wrap:wrap; gap:9px; padding:10px 4px 0; color:#e5edfb; font-size:.9rem; }
        .menu-indicator { width:8px; height:8px; border-radius:50%; background:var(--accent); display:inline-block; }
        .tab-summary-hint { color:var(--muted); margin-left:auto; }
        .tab-panel { margin-top:14px; background:transparent; border:0; padding:0; }
        .tab-panel[hidden] { display:none !important; }
        .tab-panel > section { align-self:start; }
        @media (max-width:600px) { main { width:min(100% - 20px,1000px); padding-top:20px; } .global-menu { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); } .global-menu button { width:100%; padding:8px; } .global-menu button:last-child { grid-column:1/-1; } .tab-summary-hint { margin-left:0; flex-basis:100%; } }
    </style>
</head>
<body>
<main>
    <header class="app-header">
        <div class="eyebrow">Procedural sci-fi sandbox · Foundation 0.1.24</div>
        <h1>CORE</h1>
        <p class="lead">A single workbench for universe setup, character operations, exploration, and development tools.</p>
    </header>
    <nav class="global-menu" aria-label="CORE main menu" role="tablist">
        <button type="button" role="tab" id="tab-overview" aria-controls="panel-overview" aria-selected="true" tabindex="0" data-tab="overview">Overview</button>
        <button type="button" role="tab" id="tab-universe" aria-controls="panel-universe" aria-selected="false" tabindex="-1" data-tab="universe">Universe</button>
        <button type="button" role="tab" id="tab-characters" aria-controls="panel-characters" aria-selected="false" tabindex="-1" data-tab="characters">Characters &amp; Travel</button>
        <button type="button" role="tab" id="tab-discoveries" aria-controls="panel-discoveries" aria-selected="false" tabindex="-1" data-tab="discoveries">Scanning &amp; Discoveries</button>
        <button type="button" role="tab" id="tab-operations" aria-controls="panel-operations" aria-selected="false" tabindex="-1" data-tab="operations">Operations &amp; QA</button>
    </nav>
    <div class="tab-summary" aria-live="polite"><span class="menu-indicator"></span><span id="activeSectionLabel">Overview</span><span class="tab-summary-hint">Use the main menu to switch work areas.</span></div>
    <section class="tab-panel grid" id="panel-overview" role="tabpanel" aria-labelledby="tab-overview" data-panel="overview">
        <section>
            <h2>Workbench overview</h2>
            <p>Choose a work area from the global menu. Your loaded universe and character stay selected while you move between tabs.</p>
            <div class="dashboard-grid">
                <div class="dashboard-tile"><span class="dashboard-label">Selected universe</span><strong id="overviewUniverse">None loaded</strong><span class="dashboard-detail">Seed-driven universe context</span></div>
                <div class="dashboard-tile"><span class="dashboard-label">Selected character</span><strong id="overviewCharacter">None selected</strong><span class="dashboard-detail" id="overviewRole">Choose a character in Characters &amp; Travel.</span></div>
                <div class="dashboard-tile"><span class="dashboard-label">Current system</span><strong id="overviewSystem">—</strong><span class="dashboard-detail" id="overviewTravel">No travel route loaded.</span></div>
            </div>
        </section>
        <section>
            <h2>Server status</h2>
            <p>Check that the PHP application and API are responding.</p>
            <div class="actions"><button id="healthButton" type="button">Check server</button></div>
            <p id="healthStatus" class="status" aria-live="polite">Not checked yet.</p>
        </section>
    </section>
    <section class="tab-panel grid" id="panel-universe" role="tabpanel" aria-labelledby="tab-universe" data-panel="universe" hidden>
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
    </section>
    <section class="tab-panel grid" id="panel-characters" role="tabpanel" aria-labelledby="tab-characters" data-panel="characters" hidden>
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
            <h3>Character Status &amp; Travel Dashboard</h3>
            <div class="dashboard-grid" aria-live="polite" aria-atomic="true">
                <div class="dashboard-tile"><span class="dashboard-label">Selected character</span><strong id="dashboardCharacter">None selected</strong><span id="dashboardRole" class="dashboard-detail">Choose a character below.</span></div>
                <div class="dashboard-tile"><span class="dashboard-label">Current system</span><strong id="dashboardLocation">—</strong><span id="dashboardRevision" class="dashboard-detail">Revision: —</span></div>
                <div class="dashboard-tile"><span class="dashboard-label">Travel status</span><strong id="dashboardTravelStatus">No route</strong><span id="dashboardTravelDetail" class="dashboard-detail">No travel route loaded.</span></div>
                <div class="dashboard-tile"><span class="dashboard-label">Exploration</span><strong id="dashboardExploration">0 character / 0 universe</strong><span class="dashboard-detail">Visited by character / explored globally</span></div>
            </div>
            <div class="dashboard-lists">
                <div><h4>Character-visited systems</h4><p id="dashboardVisitedList" class="dashboard-list">Select a character to see visited systems.</p></div>
                <div><h4>Universe-wide explored systems</h4><p id="dashboardGlobalList" class="dashboard-list">Load a universe to see global discoveries.</p></div>
            </div>
            <h3>Character location</h3>
            <label for="characterSelect">Character</label>
            <select id="characterSelect" style="width:100%;padding:9px 12px;min-height:44px;border-radius:8px;color:var(--text);background:#0b1325;border:1px solid var(--line)"><option value="">Load characters first</option></select>
            <label for="characterSystemSelect">Move destination — one jump only</label>
            <select id="characterSystemSelect" aria-describedby="movementChoicesHelp"><option value="">Create / load a universe first</option></select>
            <p id="movementChoicesHelp" class="field-help" role="status" aria-live="polite"><strong>How movement works:</strong> This list contains only your current system and systems reachable in one jump. Distant systems are not selectable here. Use Plan a route below to find a path to any system; route planning does not move the character.</p>
            <h3>Plan a route</h3>
            <p>Choose any system in this universe to preview the shortest route. This list includes distant systems too. Planning does not move the character.</p>
            <label for="routeDestinationSelect">Route destination</label>
            <select id="routeDestinationSelect" style="width:100%;padding:9px 12px;min-height:44px;border-radius:8px;color:var(--text);background:#0b1325;border:1px solid var(--line)"><option value="">Create / load a universe first</option></select>
            <div class="actions"><button id="planRouteButton" type="button">Plan route</button><button id="startTravelButton" type="button" disabled>Start travel along route</button><button id="advanceTravelButton" type="button" class="secondary" disabled>Travel next jump</button></div>
            <p id="routeStatus" class="status" aria-live="polite">Choose a character and destination to preview a route.</p>
            <p id="travelProgressStatus" class="status" role="status" aria-live="polite">No travel route is active for the selected character.</p>
            <pre id="routeOutput" aria-live="polite">No route planned.</pre>
            <label for="characterRevision">Expected character revision</label>
            <input id="characterRevision" type="number" min="0" step="1" value="0">
            <div class="actions"><button id="moveCharacterButton" type="button">Move character</button></div>
            <p id="characterLocationStatus" class="status" aria-live="polite">New characters start in the universe's first system. Only directly connected systems are valid destinations. Repeating a move to the current system will not increment the revision.</p>
            <p id="characterExplorationStatus" class="status" aria-live="polite">Select a character to view its location and exploration history.</p>
            <pre id="characterOutput" aria-live="polite">No characters loaded.</pre>
        </section>
    </section>
    <section class="tab-panel grid" id="panel-discoveries" role="tabpanel" aria-labelledby="tab-discoveries" data-panel="discoveries" hidden>
        <section style="grid-column:1/-1">
            <h2>System scanning &amp; discoveries</h2>
            <p>Scan the character’s current system to reveal deterministic signals. Results are saved to the character; scanning a system again returns the existing record without advancing its revision.</p>
            <div class="actions"><button id="scanSystemButton" type="button" disabled>Scan current system</button></div>
            <p id="characterScanStatus" class="status" aria-live="polite">Select a character to scan its current system.</p>
            <div id="characterScanResults" class="dashboard-lists" aria-live="polite"><div><h4>Current-system scan</h4><p class="dashboard-list">No scan loaded.</p></div></div>
        </section>
    </section>
    <section class="tab-panel grid" id="panel-operations" role="tabpanel" aria-labelledby="tab-operations" data-panel="operations" hidden>
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
                <li>Character route execution with one connected jump per action</li>
                <li>Structured application logging</li>
            </ul>
            <p class="status">This is an early development scaffold, not a finished game. Accounts, character ownership, gameplay progression, and access control are not implemented yet.</p>
        </section>
    </section>
    <footer>CORE · Local development workbench · Do not expose publicly until authentication and access controls are implemented.</footer>
</main>
<script>
(() => {
    const tabs = Array.from(document.querySelectorAll('[role="tab"][data-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-panel]'));
    const sectionLabel = document.getElementById('activeSectionLabel');
    const sectionNames = { overview: 'Overview', universe: 'Universe', characters: 'Characters & Travel', discoveries: 'Scanning & Discoveries', operations: 'Operations & QA' };
    function activateTab(name, moveFocus = false) {
        const tab = tabs.find(item => item.dataset.tab === name);
        if (!tab) return;
        tabs.forEach(item => {
            const active = item === tab;
            item.setAttribute('aria-selected', active ? 'true' : 'false');
            item.tabIndex = active ? 0 : -1;
        });
        panels.forEach(panel => { panel.hidden = panel.dataset.panel !== name; });
        if (sectionLabel) sectionLabel.textContent = sectionNames[name] || name;
        if (moveFocus) tab.focus();
    }
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activateTab(tab.dataset.tab));
        tab.addEventListener('keydown', event => {
            let next = null;
            if (event.key === 'ArrowRight' || event.key === 'ArrowDown') next = (index + 1) % tabs.length;
            else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') next = (index - 1 + tabs.length) % tabs.length;
            else if (event.key === 'Home') next = 0;
            else if (event.key === 'End') next = tabs.length - 1;
            if (next !== null) { event.preventDefault(); activateTab(tabs[next].dataset.tab, true); }
        });
    });
    activateTab('overview');
    const output = document.getElementById('output');
    const healthStatus = document.getElementById('healthStatus');
    const universeStatus = document.getElementById('universeStatus');
    const seedInput = document.getElementById('seed');
    let currentUniverseBaseline = null;
    let currentUniverseState = null;
    let currentNavigation = null;
    const activityLog = document.getElementById('activityLog');
    let loadedCharacters = [];
    function populateSystemChoices() {
        const select = document.getElementById('characterSystemSelect');
        const help = document.getElementById('movementChoicesHelp');
        const systems = currentUniverseBaseline?.systems || [];
        const routeSelect = document.getElementById('routeDestinationSelect');
        const previousDestination = select.value;
        const previousRouteDestination = routeSelect.value;
        select.replaceChildren();
        routeSelect.replaceChildren();

        if (!systems.length) {
            const option = document.createElement('option'); option.value = ''; option.textContent = 'Create / load a universe first'; select.append(option);
            const routeOption = document.createElement('option'); routeOption.value = ''; routeOption.textContent = 'Create / load a universe first'; routeSelect.append(routeOption);
            help.textContent = 'Create or load a universe, then select a character. Movement destinations will show only the current system and directly connected systems.';
            return;
        }

        for (const system of systems) {
            const routeOption = document.createElement('option');
            routeOption.value = system.id;
            routeOption.textContent = system.name + ' — ' + system.id + ' (' + system.classification + ')';
            routeSelect.append(routeOption);
        }

        const characterId = document.getElementById('characterSelect').value;
        const character = loadedCharacters.find(item => item.id === characterId);
        if (!character) {
            const option = document.createElement('option'); option.value = ''; option.textContent = 'Select a character first'; select.append(option);
            help.textContent = 'Select a character first. The movement list will then show only the current system and directly connected systems. Use Plan a route below to choose any system in the universe.';
            if (previousRouteDestination && systems.some(system => system.id === previousRouteDestination)) routeSelect.value = previousRouteDestination;
            return;
        }

        const currentId = character.current_system_id || character.location?.system_id || '';
        const reachableIds = currentNavigation?.neighbors?.[currentId] || [];
        const reachable = new Set(reachableIds);
        const allowedSystems = systems.filter(system => system.id === currentId || reachable.has(system.id));
        for (const system of allowedSystems) {
            const option = document.createElement('option');
            option.value = system.id;
            const isCurrent = system.id === currentId;
            option.textContent = system.name + ' — ' + system.id + (isCurrent ? ' [CURRENT SYSTEM]' : ' [ONE-JUMP DESTINATION]');
            select.append(option);
        }

        if (previousDestination && allowedSystems.some(system => system.id === previousDestination)) select.value = previousDestination;
        else select.value = currentId;
        if (previousRouteDestination && systems.some(system => system.id === previousRouteDestination)) routeSelect.value = previousRouteDestination;
        else routeSelect.value = currentId;

        help.innerHTML = '<strong>Showing ' + allowedSystems.length + ' selectable system(s):</strong> current system plus directly connected one-jump destinations. No greyed-out choices are shown. For a farther destination, select it in <strong>Route destination</strong> below and click <strong>Plan route</strong>. Planning a route does not move the character.';
        if (!currentNavigation) {
            help.textContent = 'Navigation data has not loaded. Refresh or create/load the universe again before moving. Route planning is separate and does not move the character.';
            select.replaceChildren();
            const option = document.createElement('option'); option.value = ''; option.textContent = 'Navigation data unavailable'; select.append(option);
        }
    }
    function renderCharacterDashboard(character = null) {
        const systems = currentUniverseBaseline?.systems || [];
        const names = new Map(systems.map(system => [system.id, system.name]));
        const globallyExplored = currentUniverseState?.projections?.explored_systems || [];
        const visited = character?.exploration?.explored_systems || [];
        const route = character?.route_progress || null;
        const currentId = character ? (character.current_system_id || character.location?.system_id || '') : '';
        const set = (id, value) => { const node = document.getElementById(id); if (node) node.textContent = value; };
        set('dashboardCharacter', character?.name || 'None selected');
        set('overviewUniverse', currentUniverseBaseline ? (currentUniverseBaseline.name || document.getElementById('seed').value.trim() || 'Loaded universe') : 'None loaded');
        set('overviewCharacter', character?.name || 'None selected');
        set('overviewRole', character ? ((character.role || 'Role not set') + ' · ' + character.id) : 'Choose a character in Characters & Travel.');
        set('overviewSystem', character ? (names.get(currentId) || currentId || 'Unknown') : '—');
        set('dashboardRole', character ? (character.role || 'Role not set') + ' · ' + character.id : 'Choose a character below.');
        set('dashboardLocation', character ? (names.get(currentId) || currentId || 'Unknown') : '—');
        set('dashboardRevision', character ? 'Character revision: ' + (character.revision ?? 0) : 'Revision: —');
        const total = route ? Number(route.total_hops ?? Math.max(0, (route.path_system_ids || []).length - 1)) : 0;
        const done = route ? Number(route.current_step_index || 0) : 0;
        const nextId = route?.status === 'active' ? (route.path_system_ids || [])[done + 1] : '';
        const statusLabel = !route ? 'No route' : ({active:'In transit',completed:'Route completed',cancelled:'Route cancelled'}[route.status] || 'Route status: ' + route.status);
        set('dashboardTravelStatus', statusLabel);
        set('dashboardTravelDetail', !character ? 'Select a character.' : route?.status === 'active' ? `${done} of ${total} jumps complete · Next: ${names.get(nextId) || nextId || 'Unavailable'}` : route?.status === 'completed' ? `${done} of ${total} jumps complete · Arrived at ${names.get(currentId) || currentId}` : route?.status === 'cancelled' ? 'Previous route was cancelled.' : 'Plan a route to begin multi-hop travel.');
        set('overviewTravel', !character ? 'No travel route loaded.' : route?.status === 'active' ? `${done}/${total} jumps complete · Next: ${names.get(nextId) || nextId || 'Unavailable'}` : route?.status === 'completed' ? 'Travel completed.' : route?.status === 'cancelled' ? 'Route cancelled.' : 'No active route.');
        set('dashboardExploration', `${visited.length} character / ${globallyExplored.length} universe`);
        set('dashboardVisitedList', character ? (visited.length ? visited.map(entry => entry.name || names.get(entry.system_id) || entry.system_id).join(' · ') : 'No systems recorded yet.') : 'Select a character to see visited systems.');
        set('dashboardGlobalList', currentUniverseBaseline ? (globallyExplored.length ? globallyExplored.map(entry => entry.name || names.get(entry.system_id) || entry.system_id).join(' · ') : 'No universe-wide exploration events recorded yet.') : 'Load a universe to see global discoveries.');
    }

    function updateCharacterDetails() {
        const systems = currentUniverseBaseline?.systems || [];
        const routeSelect = document.getElementById('routeDestinationSelect');
        routeSelect.replaceChildren();
        for (const system of systems) {
            const routeOption = document.createElement('option');
            routeOption.value = system.id;
            routeOption.textContent = system.name + ' — ' + system.id;
            routeSelect.append(routeOption);
        }
        const characterId = document.getElementById('characterSelect').value;
        const character = loadedCharacters.find(item => item.id === characterId);
        const status = document.getElementById('characterExplorationStatus');
        if (!character) {
            renderCharacterDashboard(null);
            document.getElementById('scanSystemButton').disabled = true;
            document.getElementById('characterScanStatus').textContent = 'Select a character to scan its current system.';
            document.getElementById('characterScanResults').innerHTML = '<div><h4>Current-system scan</h4><p class="dashboard-list">No scan loaded.</p></div>';
            status.textContent = 'Select a character to view its location and exploration history.';
            document.getElementById('travelProgressStatus').textContent = 'Select a character before starting or continuing travel.';
            document.getElementById('startTravelButton').disabled = true;
            document.getElementById('advanceTravelButton').disabled = true;
            return;
        }
        const names = new Map(systems.map(system => [system.id, system.name]));
        renderCharacterDashboard(character);
        const currentId = character.current_system_id || character.location?.system_id || '';
        const scanRecords = character.scans?.systems || {};
        const currentScan = scanRecords[currentId] || null;
        document.getElementById('scanSystemButton').disabled = false;
        document.getElementById('characterScanStatus').textContent = currentScan
            ? 'Current system already scanned on ' + new Date(currentScan.scanned_at).toLocaleString() + '. ' + (currentScan.discoveries || []).length + ' discovery signal(s) recorded.'
            : 'Current system has not been scanned by this character yet.';
        const scanResults = document.getElementById('characterScanResults');
        scanResults.replaceChildren();
        const scanPanel = document.createElement('div');
        const scanHeading = document.createElement('h4');
        scanHeading.textContent = currentScan ? 'Scan: ' + (currentScan.system_name || currentId) : 'Current-system scan';
        scanPanel.append(scanHeading);
        if (currentScan && Array.isArray(currentScan.discoveries) && currentScan.discoveries.length) {
            const list = document.createElement('ul'); list.className = 'dashboard-list';
            for (const discovery of currentScan.discoveries) {
                const item = document.createElement('li');
                const title = document.createElement('strong'); title.textContent = discovery.label || discovery.type || 'Unknown signal';
                const details = document.createElement('p'); details.className = 'dashboard-list';
                details.textContent = (discovery.description || 'No additional data.') + (discovery.status === 'investigated' ? ' Status: Investigated.' : ' Status: Uninvestigated.');
                item.append(title, details);
                if (discovery.status === 'investigated') {
                    const outcome = document.createElement('p'); outcome.className = 'dashboard-list'; outcome.textContent = 'Investigation result: ' + (discovery.outcome || 'No result recorded.'); item.append(outcome);
                } else {
                    const investigate = document.createElement('button'); investigate.type = 'button'; investigate.className = 'secondary investigate-discovery'; investigate.dataset.discoveryId = discovery.id || ''; investigate.textContent = 'Investigate signal'; investigate.disabled = !characterId; item.append(investigate);
                }
                list.append(item);
            }
            scanPanel.append(list);
        } else {
            const message = document.createElement('p'); message.className = 'dashboard-list'; message.textContent = 'No saved scan results for the current system.'; scanPanel.append(message);
        }
        scanResults.append(scanPanel);
        const historyPanel = document.createElement('div');
        const historyHeading = document.createElement('h4'); historyHeading.textContent = 'Character scan history'; historyPanel.append(historyHeading);
        const scanEntries = Object.values(scanRecords).filter(entry => entry && typeof entry === 'object');
        if (scanEntries.length) {
            const historyList = document.createElement('ul'); historyList.className = 'dashboard-list';
            for (const entry of scanEntries) {
                const item = document.createElement('li');
                item.textContent = (entry.system_name || entry.system_id || 'Unknown system') + ' — ' + (entry.discoveries || []).length + ' signal(s)';
                historyList.append(item);
            }
            historyPanel.append(historyList);
        } else {
            const emptyHistory = document.createElement('p'); emptyHistory.className = 'dashboard-list'; emptyHistory.textContent = 'No systems scanned yet.'; historyPanel.append(emptyHistory);
        }
        scanResults.append(historyPanel);
        const visited = character.exploration?.explored_systems || [];
        const visitedNames = visited.map(entry => entry.name || names.get(entry.system_id) || entry.system_id).filter(Boolean);
        status.textContent = 'Current system: ' + (names.get(currentId) || currentId || 'Unknown') + ' (' + currentId + '). Explored systems (' + visited.length + '): ' + (visitedNames.length ? visitedNames.join(', ') : 'none recorded');
        const progressStatus = document.getElementById('travelProgressStatus');
        const advanceButton = document.getElementById('advanceTravelButton');
        const routeProgress = character.route_progress;
        if (routeProgress && routeProgress.status === 'active') {
            const path = Array.isArray(routeProgress.path_system_ids) ? routeProgress.path_system_ids : [];
            const index = Number(routeProgress.current_step_index || 0);
            const completedHops = index;
            const totalHops = Number(routeProgress.total_hops || Math.max(0, path.length - 1));
            const nextId = path[index + 1] || '';
            progressStatus.textContent = 'Travel active: ' + completedHops + ' of ' + totalHops + ' jump(s) complete. Current: ' + (names.get(currentId) || currentId) + (nextId ? '. Next jump: ' + (names.get(nextId) || nextId) + ' (' + nextId + ').' : '.');
            // Keep the control actionable while a character is selected. If route state is
            // stale or malformed, clicking still produces a visible explanation instead of
            // looking like a dead control.
            advanceButton.disabled = false;
        } else if (routeProgress && routeProgress.status === 'completed') {
            progressStatus.textContent = 'Last travel route completed at ' + (names.get(currentId) || currentId) + '. Choose a destination and start a new route to travel again.';
            advanceButton.disabled = !characterId;
        } else if (routeProgress && routeProgress.status === 'cancelled') {
            progressStatus.textContent = 'Previous route was cancelled. Plan and start a new route to continue travelling.';
            advanceButton.disabled = !characterId;
        } else {
            progressStatus.textContent = 'No travel route is active for this character. Plan a route, then click Start travel along route.';
            advanceButton.disabled = !characterId;
        }
        document.getElementById('startTravelButton').disabled = !characterId;
    }
    function populateCharacterChoices() {
        const select = document.getElementById('characterSelect');
        const prior = select.value;
        select.replaceChildren();
        if (!loadedCharacters.length) {
            const option = document.createElement('option'); option.value = ''; option.textContent = 'No characters in this universe'; select.append(option);
            updateCharacterDetails();
            populateSystemChoices();
            return;
        }
        for (const character of loadedCharacters) {
            const option = document.createElement('option'); option.value = character.id;
            option.textContent = character.name + ' — ' + character.id;
            option.dataset.revision = String(character.revision ?? 0);
            select.append(option);
        }
        if (loadedCharacters.some(character => character.id === prior)) select.value = prior;
        document.getElementById('characterRevision').value = select.selectedOptions[0]?.dataset.revision ?? '0';
        updateCharacterDetails();
        populateSystemChoices();
    }
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
                currentUniverseState = { ...(currentUniverseState || {}), ...data.state };
                renderCharacterDashboard(loadedCharacters.find(item => item.id === document.getElementById('characterSelect').value) || null);
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
    document.getElementById('characterSelect').addEventListener('change', () => {
        document.getElementById('characterRevision').value = document.getElementById('characterSelect').selectedOptions[0]?.dataset.revision ?? '0';
        updateCharacterDetails();
        populateSystemChoices();
    });
    document.getElementById('planRouteButton').addEventListener('click', async () => {
        const universeId = document.getElementById('characterUniverseId').value.trim();
        const characterId = document.getElementById('characterSelect').value;
        const destinationId = document.getElementById('routeDestinationSelect').value;
        const character = loadedCharacters.find(item => item.id === characterId);
        const status = document.getElementById('routeStatus');
        const routeOutput = document.getElementById('routeOutput');
        const fromId = character?.current_system_id || character?.location?.system_id || '';
        if (!universeId || !character || !fromId || !destinationId) { status.textContent = 'Create or load a universe, select a character, and choose a destination.'; return; }
        const button = document.getElementById('planRouteButton'); button.disabled = true; status.textContent = 'Planning shortest route…';
        try {
            const params = new URLSearchParams({ universe_id: universeId, from_system_id: fromId, to_system_id: destinationId });
            const response = await fetch('api/navigation-route.php?' + params.toString(), { headers: { 'Accept': 'application/json' } });
            const text = await response.text(); let data;
            try { data = JSON.parse(text); } catch { data = { ok: false, error: { message: text || 'Invalid server response' } }; }
            routeOutput.textContent = JSON.stringify(data, null, 2);
            if (response.ok && data.ok) {
                const labels = (data.path || []).map(step => step.name + ' (' + step.system_id + ')');
                status.textContent = data.hops === 0 ? 'Already at destination — 0 hops.' : 'Route planned: ' + data.hops + ' hop(s). ' + labels.join(' → ');
                logActivity('info', 'Route planned', data.hops + ' hop(s) from ' + fromId + ' to ' + destinationId);
            } else {
                status.textContent = (data.error?.code || 'route_failed') + ': ' + (data.error?.message || 'Route planning failed.');
                logActivity('error', 'Route planning failed', JSON.stringify(data));
            }
        } catch (error) { status.textContent = 'Route planning request failed.'; routeOutput.textContent = String(error); logActivity('error', 'Route planning request failed', String(error)); }
        finally { button.disabled = false; }
    });
    async function submitTravelAction(action) {
        const universeId = document.getElementById('characterUniverseId').value.trim();
        const characterId = document.getElementById('characterSelect').value;
        const destinationId = document.getElementById('routeDestinationSelect').value;
        const revision = Number(document.getElementById('characterRevision').value);
        const status = document.getElementById('travelProgressStatus');
        if (!universeId || !characterId) { status.textContent = 'Create or load a universe and select a character first.'; return; }
        if (!Number.isSafeInteger(revision) || revision < 0) { status.textContent = 'Expected character revision must be a non-negative integer.'; return; }
        if (action === 'start' && !destinationId) { status.textContent = 'Choose a route destination first.'; return; }
        const button = document.getElementById(action === 'start' ? 'startTravelButton' : 'advanceTravelButton');
        button.disabled = true;
        status.textContent = action === 'start' ? 'Starting travel route…' : 'Executing next jump…';
        try {
            const payload = { action, universe_id: universeId, character_id: characterId, expected_revision: revision };
            if (action === 'start') payload.destination_system_id = destinationId;
            const response = await fetch('api/character-travel.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(payload) });
            const text = await response.text(); let data;
            try { data = JSON.parse(text); } catch { data = { ok: false, error: { message: text || 'Invalid server response' } }; }
            document.getElementById('characterOutput').textContent = JSON.stringify(data, null, 2);
            if (!response.ok || !data.ok) {
                const failureMessage = (data.error?.code || 'travel_failed') + ': ' + (data.error?.message || 'Travel action failed.');
                logActivity('error', 'Character travel failed', JSON.stringify(data));
                // Refresh server state after conflicts/rejections so the revision and route
                // status shown in the Workbench cannot remain stale.
                await refreshCharacters();
                document.getElementById('characterSelect').value = characterId;
                const latestCharacter = loadedCharacters.find(item => item.id === characterId);
                if (latestCharacter) document.getElementById('characterRevision').value = String(latestCharacter.revision ?? 0);
                updateCharacterDetails();
                populateSystemChoices();
                status.textContent = failureMessage;
                return;
            }
            const character = data.character;
            await refreshCharacters();
            document.getElementById('characterSelect').value = characterId;
            document.getElementById('characterRevision').value = String(character.revision);
            updateCharacterDetails();
            populateSystemChoices();
            const progress = character.route_progress || {};
            const path = progress.path_system_ids || [];
            const names = new Map((currentUniverseBaseline?.systems || []).map(system => [system.id, system.name]));
            const routeText = path.map(id => (names.get(id) || id) + ' (' + id + ')').join(' → ');
            document.getElementById('routeOutput').textContent = JSON.stringify({ ok: true, travel_status: data.travel_status, character_id: characterId, current_system_id: character.current_system_id, revision: character.revision, route_progress: progress, route: routeText }, null, 2);
            status.textContent = data.travel_status === 'already_at_destination'
                ? 'Already at the selected destination. No jumps required.'
                : data.travel_status === 'route_completed'
                    ? 'Travel complete. Character reached ' + (names.get(character.current_system_id) || character.current_system_id) + '.'
                    : data.travel_status === 'route_started'
                        ? 'Travel started: ' + Math.max(0, path.length - 1) + ' jump(s). Click Travel next jump to execute one jump at a time.'
                        : 'Jump completed. ' + (progress.current_step_index || 0) + ' of ' + (progress.total_hops || 0) + ' jump(s) complete.';
            logActivity('info', 'Character travel ' + action, characterId + ' → ' + character.current_system_id + ' (' + data.travel_status + ', revision ' + character.revision + ')');
        } catch (error) {
            status.textContent = 'Travel request failed: ' + String(error);
            logActivity('error', 'Character travel request failed', String(error));
        } finally {
            if (action === 'start') {
                button.disabled = !document.getElementById('characterSelect').value;
            } else {
                const selectedCharacter = loadedCharacters.find(item => item.id === document.getElementById('characterSelect').value);
                // Do not leave a selected character with an apparently dead button. If no
                // route is active, the next click will return a visible explanation.
                button.disabled = !selectedCharacter;
            }
        }
    }
    document.getElementById('startTravelButton').addEventListener('click', () => submitTravelAction('start'));
    document.getElementById('advanceTravelButton').addEventListener('click', () => submitTravelAction('advance'));

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
                status.textContent = data.movement_status === 'already_at_destination'
                    ? 'Character is already at this destination. Revision unchanged: ' + data.character.revision + '.'
                    : 'Character moved to ' + systemId + '. Revision: ' + data.character.revision + '.';
                await refreshCharacters();
                document.getElementById('characterSelect').value = characterId;
                document.getElementById('characterRevision').value = String(data.character.revision);
                logActivity('info', data.movement_status === 'already_at_destination' ? 'Character already at destination' : 'Character moved', characterId + ' → ' + systemId + ' (revision ' + data.character.revision + ')');
            } else {
                status.textContent = (data.error?.code || 'request_failed') + ': ' + (data.error?.message || 'Character move failed.');
                logActivity('error', 'Character move failed', JSON.stringify(data));
            }
        } catch (error) { status.textContent = 'Character move request failed.'; logActivity('error', 'Character move request failed', String(error)); }
        finally { button.disabled = false; }
    });
    document.getElementById('scanSystemButton').addEventListener('click', async () => {
        const universeId = document.getElementById('characterUniverseId').value.trim();
        const characterId = document.getElementById('characterSelect').value;
        const revision = Number(document.getElementById('characterRevision').value);
        const status = document.getElementById('characterScanStatus');
        if (!universeId || !characterId) { status.textContent = 'Create or load a universe and select a character first.'; return; }
        if (!Number.isSafeInteger(revision) || revision < 0) { status.textContent = 'Expected character revision must be a non-negative integer.'; return; }
        const button = document.getElementById('scanSystemButton'); button.disabled = true; status.textContent = 'Scanning current system and saving discoveries…';
        try {
            const response = await fetch('api/character-scan.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ universe_id: universeId, character_id: characterId, expected_revision: revision }) });
            const text = await response.text(); let data;
            try { data = JSON.parse(text); } catch { data = { ok: false, error: { message: text || 'Invalid server response' } }; }
            document.getElementById('characterOutput').textContent = JSON.stringify(data, null, 2);
            if (response.ok && data.ok) {
                const discoveries = data.scan?.discoveries || [];
                status.textContent = data.scan_status === 'already_scanned'
                    ? 'This system was already scanned. Returning ' + discoveries.length + ' saved discovery signal(s); revision unchanged at ' + data.character.revision + '.'
                    : 'Scan complete in ' + (data.scan?.system_name || data.scan?.system_id) + ': ' + discoveries.length + ' discovery signal(s). Character revision: ' + data.character.revision + '.';
                await refreshCharacters();
                document.getElementById('characterSelect').value = characterId;
                document.getElementById('characterRevision').value = String(data.character.revision);
                updateCharacterDetails();
                logActivity('info', 'System scan ' + data.scan_status, (data.scan?.system_name || data.scan?.system_id || '') + ' — ' + discoveries.length + ' discovery signal(s)');
            } else {
                status.textContent = (data.error?.code || 'request_failed') + ': ' + (data.error?.message || 'System scan failed.');
                logActivity('error', 'System scan failed', JSON.stringify(data));
            }
        } catch (error) { status.textContent = 'System scan request failed. Check the server and activity log.'; logActivity('error', 'System scan request failed', String(error)); }
        finally { button.disabled = !document.getElementById('characterSelect').value; }
    });

    document.getElementById('characterScanResults').addEventListener('click', async event => {
        const button = event.target.closest('button.investigate-discovery');
        if (!button) return;
        const universeId = document.getElementById('characterUniverseId').value.trim();
        const characterId = document.getElementById('characterSelect').value;
        const revision = Number(document.getElementById('characterRevision').value);
        const status = document.getElementById('characterScanStatus');
        const discoveryId = button.dataset.discoveryId || '';
        if (!universeId || !characterId || !discoveryId) { status.textContent = 'Load a universe, select a character, and choose a saved discovery.'; return; }
        if (!Number.isSafeInteger(revision) || revision < 0) { status.textContent = 'Expected character revision must be a non-negative integer.'; return; }
        button.disabled = true;
        status.textContent = 'Investigating discovery…';
        try {
            const response = await fetch('api/character-discovery-investigate.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ universe_id: universeId, character_id: characterId, discovery_id: discoveryId, expected_revision: revision }) });
            const text = await response.text(); let data;
            try { data = JSON.parse(text); } catch { data = { ok: false, error: { message: text || 'Invalid server response' } }; }
            document.getElementById('characterOutput').textContent = JSON.stringify(data, null, 2);
            if (response.ok && data.ok) {
                status.textContent = data.investigation_status === 'already_investigated' ? 'This discovery was already investigated. Saved result returned; revision unchanged at ' + data.character.revision + '.' : 'Investigation complete: ' + (data.discovery.label || data.discovery.id) + '. Character revision: ' + data.character.revision + '.';
                logActivity('info', 'Discovery ' + data.investigation_status, (data.discovery.label || discoveryId) + ' — ' + (data.discovery.outcome || 'saved'));
                await refreshCharacters();
                document.getElementById('characterSelect').value = characterId;
                document.getElementById('characterRevision').value = String(data.character.revision);
                updateCharacterDetails();
            } else {
                status.textContent = (data.error?.code || 'investigation_failed') + ': ' + (data.error?.message || 'Discovery investigation failed.');
                logActivity('error', 'Discovery investigation failed', JSON.stringify(data));
                if (response.status === 409) {
                    await refreshCharacters();
                    document.getElementById('characterSelect').value = characterId;
                    const latest = loadedCharacters.find(item => item.id === characterId);
                    if (latest) document.getElementById('characterRevision').value = String(latest.revision ?? 0);
                    updateCharacterDetails();
                }
            }
        } catch (error) {
            status.textContent = 'Discovery investigation request failed. Check the server and activity log.';
            logActivity('error', 'Discovery investigation request failed', String(error));
        } finally {
            const selected = loadedCharacters.find(item => item.id === document.getElementById('characterSelect').value);
            button.disabled = !selected;
        }
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
            if (response.ok && data.ok) {
                await refreshCharacters();
                document.getElementById('characterSelect').value = data.character.id;
                document.getElementById('characterRevision').value = String(data.character.revision ?? 0);
                updateCharacterDetails();
                populateSystemChoices();
            }
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
                currentUniverseState = result.data.universe.state || null;
                renderCharacterDashboard(null);
                document.getElementById('stateUniverseId').value = result.data.universe.id || '';
                document.getElementById('characterUniverseId').value = result.data.universe.id || '';
                document.getElementById('characterStatus').textContent = 'Universe selected. You can create or list its characters.';
                document.getElementById('stateRevision').value = result.data.universe.state?.revision ?? 0;
                document.getElementById('stateStatus').textContent = 'Universe selected. Confirm the ID and expected revision before updating.';
                currentNavigation = null;
                try {
                    const navResponse = await fetch('api/navigation.php?universe_id=' + encodeURIComponent(result.data.universe.id), { headers: { 'Accept': 'application/json' } });
                    const navData = await navResponse.json();
                    if (navResponse.ok && navData.ok) currentNavigation = navData.navigation;
                    else logActivity('error', 'Navigation graph', JSON.stringify(navData));
                } catch (navError) { logActivity('error', 'Navigation graph request failed', String(navError)); }
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
