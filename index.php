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
        .status { color:var(--muted); min-height:1.5em; margin-top:8px; overflow-wrap:anywhere; }
        .status.error { color:#fff; background:#642c38; border:1px solid #ff879a; border-left:5px solid #ff879a; border-radius:7px; padding:10px 12px; font-weight:650; }
        .status.success { color:#eafff9; background:#16463f; border:1px solid #70d6c2; border-left:5px solid #70d6c2; border-radius:7px; padding:10px 12px; }
        .status.info { color:#fff; background:#202e48; border-left:4px solid var(--accent); border-radius:6px; padding:9px 12px; }
        #universeConfirmationPhrase { flex:1 1 320px; min-width:0; font-family:ui-monospace,SFMono-Regular,Consolas,monospace; font-weight:700; }
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
        .form-row { display:grid; grid-template-columns:minmax(0,1fr); gap:6px; max-width:680px; margin:12px 0; }
        .form-row button { justify-self:start; max-width:100%; }
        .content-studio-grid { display:grid; grid-template-columns:minmax(220px,.8fr) minmax(0,2fr); gap:14px; margin:14px 0; }
        .content-studio-grid > section { background:#101a2e; padding:16px; }
        .content-studio-grid h3 { margin:0 0 12px; }
        .content-field-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr)); gap:12px; }
        .content-field { min-width:0; margin-bottom:12px; }
        .content-field textarea { min-height:86px; }
        .content-input-row { display:grid; grid-template-columns:minmax(0,2fr) minmax(90px,1fr) auto; gap:8px; align-items:end; margin:8px 0; }
        .content-input-row button { margin:0; min-height:44px; }
        .content-reward-grid { display:grid; grid-template-columns:minmax(0,2fr) repeat(2,minmax(90px,1fr)); gap:8px; }
        details { border:1px solid var(--line); border-radius:8px; padding:12px; margin-top:16px; background:#0d1628; }
        summary { cursor:pointer; font-weight:700; }
        @media(max-width:700px) { .content-studio-grid { grid-template-columns:minmax(0,1fr); } .content-input-row,.content-reward-grid { grid-template-columns:minmax(0,1fr); } }

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
        <div class="eyebrow">Procedural sci-fi sandbox · Foundation 0.1.44</div>
        <h1>CORE</h1>
        <p class="lead">A single workbench for universe setup, character operations, exploration, and development tools.</p>
    </header>
    <nav class="global-menu" aria-label="CORE main menu" role="tablist">
        <button type="button" role="tab" id="tab-overview" aria-controls="panel-overview" aria-selected="true" tabindex="0" data-tab="overview">Overview</button>
        <button type="button" role="tab" id="tab-universe" aria-controls="panel-universe" aria-selected="false" tabindex="-1" data-tab="universe">Universe</button>
        <button type="button" role="tab" id="tab-characters" aria-controls="panel-characters" aria-selected="false" tabindex="-1" data-tab="characters">Characters &amp; Travel</button>
        <button type="button" role="tab" id="tab-discoveries" aria-controls="panel-discoveries" aria-selected="false" tabindex="-1" data-tab="discoveries">Scanning &amp; Discoveries</button>
        <button type="button" role="tab" id="tab-content" aria-controls="panel-content" aria-selected="false" tabindex="-1" data-tab="content">Content Management</button>
        <button type="button" role="tab" id="tab-data" aria-controls="panel-data" aria-selected="false" tabindex="-1" data-tab="data">Data Management</button>
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
        <section>
            <h2>Universe lifecycle</h2>
            <p>Disable a universe to take it out of gameplay, or lock it to freeze gameplay mutations. Both actions are reversible and preserve saved data. Existing characters and records remain available for inspection.</p>
            <p id="universeLifecycleStatus" class="status" aria-live="polite">Create or load a universe to manage its lifecycle.</p>
            <div class="actions">
                <button id="disableUniverseButton" type="button" disabled>Disable universe</button>
                <button id="enableUniverseButton" type="button" disabled>Enable universe</button>
                <button id="lockUniverseButton" type="button" disabled>Lock universe</button>
                <button id="unlockUniverseButton" type="button" disabled>Unlock universe</button>
            </div>
            <label for="universeLifecycleRevision">Expected lifecycle revision</label>
            <input id="universeLifecycleRevision" type="number" min="0" step="1" value="0" readonly>
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
            <section style="margin-top:16px;border-color:#526e94;background:#111f35">
                <h3 style="margin-top:0">Character controls: disable or lock</h3>
                <p><strong>Disable</strong> temporarily takes a character out of use while preserving all progress. <strong>Lock</strong> freezes gameplay changes while leaving the record visible. Both are reversible; neither deletes data. You can disable and lock independently.</p>
                <p id="characterLifecycleStatus" class="status" role="status" aria-live="polite">Select a character to view its enabled/locked state.</p>
                <div class="actions">
                    <button id="disableCharacterButton" type="button" class="secondary" disabled>Disable character</button>
                    <button id="enableCharacterButton" type="button" class="secondary" disabled>Enable character</button>
                    <button id="lockCharacterButton" type="button" class="secondary" disabled>Lock character</button>
                    <button id="unlockCharacterButton" type="button" class="secondary" disabled>Unlock character</button>
                </div>
            </section>
            <section style="margin-top:16px;border-color:#8b4545;background:#211725">
                <h3 style="color:#ffb8b8;margin-top:0">Danger zone: delete character</h3>
                <p>This permanently deletes the selected character record, including its location, exploration history, discoveries, inventory, crafting ledger, travel progress, and ship loadout. It does not delete the universe or other characters. This cannot be undone.</p>
                <div class="actions"><button id="deleteCharacterButton" type="button" style="background:#ffb8b8;color:#321010" disabled>Delete selected character…</button></div>
                <p id="deleteCharacterStatus" class="status" role="status" aria-live="polite">Select a character to enable deletion.</p>
            </section>
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
            <h3>Character inventory</h3>
            <p>Recovered materials are character-bound for now. Each discovery can award its reward only once.</p>
            <div id="characterInventory" class="dashboard-lists" aria-live="polite"><div><h4>Inventory</h4><p class="dashboard-list">Select a character to view recovered resources.</p></div></div>
            <h3>Crafting bench</h3>
            <p>Convert recovered materials into useful intermediate goods. Crafting consumes the listed inputs and saves all changes in one character JSON transaction. Recipe definitions are editable JSON, not PHP constants: <a href="api/content-recipes.php" target="_blank" rel="noopener">view recipe API</a> · <a href="api/content-items.php" target="_blank" rel="noopener">view item API</a>.</p>
            <div class="form-row">
                <label for="craftRecipe">Recipe</label>
                <select id="craftRecipe"><option value="">Loading JSON recipe catalog…</option></select>
                <label for="craftQuantity">Batches (1–10)</label>
                <input id="craftQuantity" type="number" min="1" max="10" step="1" value="1">
                <button id="craftButton" type="button" disabled>Craft selected recipe</button>
            </div>
            <p id="craftStatus" class="status" role="status" aria-live="polite">Select a character and collect materials to use the crafting bench.</p>
            <h3>Ship fitting</h3>
            <p>Install one module per slot. Installation consumes inventory materials permanently and is saved to the selected character JSON. Module definitions are editable JSON: <a href="api/content-ship-modules.php" target="_blank" rel="noopener">view ship-module API</a>. Discovery and reward definitions: <a href="api/content-discoveries.php" target="_blank" rel="noopener">view discovery API</a>.</p>
            <div class="form-row">
                <label for="fitModule">Ship module</label>
                <select id="fitModule"><option value="">Loading JSON ship-module catalog…</option></select>
                <button id="fitModuleButton" type="button" disabled>Install module</button>
            </div>
            <p id="fitModuleStatus" class="status" role="status" aria-live="polite">Collect materials and select a character to install ship modules.</p>
            <div id="shipFittingPanel" class="dashboard-lists" aria-live="polite"><div><h4>Installed modules</h4><p class="dashboard-list">Select a character to view ship fittings.</p></div></div>
            <div id="characterScanResults" class="dashboard-lists" aria-live="polite"><div><h4>Current-system scan</h4><p class="dashboard-list">No scan loaded.</p></div></div>
        </section>
    </section>
    <section class="tab-panel grid" id="panel-content" role="tabpanel" aria-labelledby="tab-content" data-panel="content" hidden>
        <section style="grid-column:1/-1">
            <h2>Content Studio</h2>
            <p>Edit game definitions through structured forms. CORE stores the definitions as JSON, but routine content changes no longer require editing JSON files. Every catalog is validated against related catalogs before it can be saved.</p>
            <p class="status info">Development-only management. Existing content IDs are stable and cannot be renamed in the form editor because saved inventories, discoveries, recipes, and installed modules may reference them. Use a new ID to add content.</p>
            <div class="form-row">
                <label for="contentCatalogSelect">Content type</label>
                <select id="contentCatalogSelect">
                    <option value="items">Items &amp; resources</option>
                    <option value="recipes">Crafting recipes</option>
                    <option value="discoveries">Discoveries &amp; rewards</option>
                    <option value="ship_modules">Ship modules</option>
                </select>
                <div class="actions">
                    <button id="contentLoadButton" type="button" class="secondary">Reload from server</button>
                    <button id="contentValidateButton" type="button" class="secondary">Validate changes</button>
                    <button id="contentSaveButton" type="button">Save catalog</button>
                </div>
            </div>
            <div class="content-studio-grid">
                <section class="content-entry-browser">
                    <h3>Entries</h3>
                    <label for="contentEntrySelect">Choose an entry</label>
                    <select id="contentEntrySelect"><option value="">Load a catalog first</option></select>
                    <div class="actions">
                        <button id="contentAddEntryButton" type="button" class="secondary">+ New entry</button>
                        <button id="contentDeleteEntryButton" type="button" class="secondary">Remove entry</button>
                    </div>
                    <p id="contentEntryHelp" class="dashboard-list">Select an entry to edit its fields.</p>
                </section>
                <section class="content-entry-editor">
                    <h3 id="contentEntryHeading">Entry editor</h3>
                    <div id="contentEntryForm"><p>Load a catalog to edit entries.</p></div>
                    <div class="actions"><button id="contentApplyEntryButton" type="button">Apply entry changes</button></div>
                </section>
            </div>
            <details id="contentAdvancedDetails">
                <summary>Advanced: inspect or edit raw JSON</summary>
                <p id="contentEditorHelp" class="dashboard-list">Use this only for catalog-level fields or advanced edits. Structured editing is the normal workflow. JSON edits are validated before saving.</p>
                <label for="contentJsonEditor">Catalog JSON</label>
                <textarea id="contentJsonEditor" rows="22" spellcheck="false" autocomplete="off" style="width:100%;min-height:360px;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;tab-size:2" aria-describedby="contentEditorHelp"></textarea>
                <div class="actions"><button id="contentUseJsonButton" type="button" class="secondary">Use JSON draft in editor</button><button id="contentRefreshJsonButton" type="button" class="secondary">Refresh JSON from structured draft</button></div>
            </details>
            <p id="contentManagementStatus" class="status" role="status" aria-live="polite">Load a catalog to begin.</p>
            <pre id="contentManagementOutput" aria-live="polite">Content API results and validation details appear here.</pre>
        </section>
    </section>
    <section class="tab-panel grid" id="panel-data" role="tabpanel" aria-labelledby="tab-data" data-panel="data" hidden>
        <section style="grid-column:1/-1">
            <h2>Saved-data inventory</h2>
            <p>Inspect saved universes and characters without changing them. Player inventory, scan discoveries, exploration history, crafting ledger, and installed ship modules are stored in each character JSON record. Read a complete saved character through <code>api/character-record.php?character_id=...</code> or list complete records through <code>api/characters.php?universe_id=...</code>. This tool only reads the JSON collections; it never lists or touches operational logs.</p>
            <div class="actions"><button id="refreshDataInventoryButton" type="button">Refresh inventory</button></div>
            <p id="dataInventoryStatus" class="status" aria-live="polite">Inventory not loaded yet.</p>
            <div class="dashboard-grid" aria-live="polite">
                <div class="dashboard-tile"><span class="dashboard-label">Universes</span><strong id="dataUniverseCount">—</strong></div>
                <div class="dashboard-tile"><span class="dashboard-label">Characters</span><strong id="dataCharacterCount">—</strong></div>
                <div class="dashboard-tile"><span class="dashboard-label">Generated systems</span><strong id="dataSystemCount">—</strong></div>
                <div class="dashboard-tile"><span class="dashboard-label">Universe events</span><strong id="dataEventCount">—</strong></div>
            </div>
            <div class="dashboard-lists">
                <div><h4>Saved universes</h4><div id="dataUniverseList" class="dashboard-list">Refresh inventory to list saved universes.</div></div>
                <div><h4>Saved characters</h4><div id="dataCharacterList" class="dashboard-list">Refresh inventory to list saved characters.</div></div>
            </div>
        </section>
        <section>
            <h2>Cleanup impact preview</h2>
            <p>Preview the records and embedded gameplay data affected by a cleanup. Previewing never deletes or modifies data.</p>
            <label for="cleanupScope">Record type</label>
            <select id="cleanupScope"><option value="character">Character</option><option value="universe">Universe</option></select>
            <label for="cleanupRecordSearch">Find record by name or ID</label>
            <input id="cleanupRecordSearch" type="search" autocomplete="off" placeholder="Search names or IDs (useful with large inventories)">
            <label for="cleanupRecordId">Record <span id="cleanupRecordCount" class="dashboard-detail">Refresh inventory first</span></label>
            <select id="cleanupRecordId"><option value="">Load inventory first</option></select>
            <div class="actions"><button id="previewCleanupButton" type="button">Preview impact</button></div>
            <p id="cleanupPreviewStatus" class="status" aria-live="polite">No preview generated.</p>
            <pre id="cleanupPreviewOutput" aria-live="polite">Choose a record and preview its cleanup impact.</pre>
        </section>
        <section style="grid-column:1/-1">
            <h2>Universe backups &amp; recovery</h2>
            <p>Create a verified snapshot of one universe and its characters before cleanup. Backups are stored separately under <code>storage/backups/</code>; operational logs are never included. Restore never overwrites a conflicting live record.</p>
            <label for="backupUniverseId">Universe to back up</label>
            <select id="backupUniverseId"><option value="">Refresh inventory first</option></select>
            <div class="actions"><button id="createUniverseBackupButton" type="button">Create universe backup</button><button id="refreshBackupsButton" type="button" class="secondary">Refresh backups</button></div>
            <p id="backupStatus" class="status" aria-live="polite">No backup action performed.</p>
            <label for="backupRecordId">Saved backup</label>
            <select id="backupRecordId"><option value="">Refresh backups first</option></select>
            <label for="backupRestoreConfirmation">Type RESTORE to confirm recovery</label>
            <input id="backupRestoreConfirmation" maxlength="7" autocomplete="off" placeholder="RESTORE">
            <div class="actions"><button id="restoreUniverseBackupButton" type="button" disabled>Restore selected backup</button></div>
            <div id="backupList" class="dashboard-list">No backup inventory loaded.</div>
            <p class="status">Restore is additive and conflict-safe: it restores missing records and accepts already-identical records, but refuses to overwrite different live records. Keep external/off-host backups too; an on-disk backup does not protect against disk loss.</p>
        </section>
        <section>
            <h2>Permanent character removal</h2>
            <p>Character deletion removes only the selected character record and its embedded data. Type the exact character name and confirm.</p>
            <label for="cleanupConfirmationName">Exact character name confirmation</label>
            <input id="cleanupConfirmationName" maxlength="40" autocomplete="off" placeholder="Type the selected character name">
            <div class="actions"><button id="deleteManagedCharacterButton" type="button" disabled>Delete selected character permanently</button></div>
            <p id="cleanupDeleteStatus" class="status" aria-live="polite">Select a character and generate a character deletion preview first.</p>
        </section>
        <section>
            <h2>Permanent universe removal</h2>
            <p>Deletes the selected universe and all associated character records. CORE creates a checksum-verified backup first and retains it after deletion. Use an off-host backup for disaster recovery.</p>
            <label for="managedUniverseSearch">Find universe by name or ID</label>
            <input id="managedUniverseSearch" type="search" autocomplete="off" placeholder="Search universes by name or ID">
            <label for="managedUniverseTarget">Universe to remove</label>
            <select id="managedUniverseTarget"><option value="">Refresh inventory first</option></select>
            <div class="actions"><button id="previewUniverseDeletionButton" type="button" class="secondary">Preview selected universe deletion</button></div>
            <label for="universeConfirmationPhrase">Required confirmation phrase (select/copy this exact text)</label>
            <div class="actions"><input id="universeConfirmationPhrase" readonly value="Generate a fresh preview to show the phrase" aria-label="Required universe deletion confirmation phrase"></div>
            <label for="cleanupUniverseConfirmation">Type the exact phrase shown above</label>
            <input id="cleanupUniverseConfirmation" maxlength="220" autocomplete="off" placeholder="DELETE UNIVERSE &lt;universe-id&gt;" aria-describedby="cleanupUniverseDeleteStatus">
            <div class="actions"><button id="deleteManagedUniverseButton" type="button" disabled>Back up and delete universe</button></div>
            <p id="cleanupUniverseDeleteStatus" class="status" role="status" aria-live="assertive">Select a universe above and preview it. The deletion button stays disabled until the exact phrase matches.</p>
        </section>
    </section>
    <section class="tab-panel grid" id="panel-operations" role="tabpanel" aria-labelledby="tab-operations" data-panel="operations" hidden>
        <section style="grid-column:1/-1">
            <h2>Response</h2>
            <pre id="output" aria-live="polite">Ready. Choose an action above.</pre>
        </section>
        <section style="grid-column:1/-1">
            <h2>Workbench activity log</h2>
            <p>Browser-side actions and failures appear here. For universe deletion failures, inspect <code>storage/logs/universe-deletion.log</code> and <code>storage/logs/backups.log</code>.</p>
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
    const sectionNames = { overview: 'Overview', universe: 'Universe', characters: 'Characters & Travel', discoveries: 'Scanning & Discoveries', content: 'Content Management', data: 'Data Management', operations: 'Operations & QA' };
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
    let currentUniverseId = '';
    let currentUniverseLifecycle = { enabled: true, locked: false, revision: 0 };
    let currentNavigation = null;
    const activityLog = document.getElementById('activityLog');
    let loadedCharacters = [];
    let gameContent = { items: {}, recipes: {}, discoveries: {}, ship_modules: {} };
    async function loadGameContent() {
        const craftSelect = document.getElementById('craftRecipe');
        const moduleSelect = document.getElementById('fitModule');
        try {
            const response = await fetch('api/content.php', { headers: { 'Accept': 'application/json' } });
            const data = await response.json();
            if (!response.ok || !data.ok || !data.content) throw new Error(data.error?.message || 'Content catalog request failed.');
            for (const name of ['items', 'recipes', 'discoveries', 'ship_modules']) gameContent[name] = data.content[name]?.entries || {};
            const fill = (select, entries, describe) => {
                const previous = select.value; select.replaceChildren();
                for (const [id, definition] of Object.entries(entries)) select.add(new Option(describe(id, definition), id));
                if (previous && entries[previous]) select.value = previous;
                if (!select.options.length) select.add(new Option('No definitions configured', ''));
            };
            const itemLabel = id => gameContent.items[id]?.label || id.replaceAll('_', ' ');
            const requirements = inputs => Object.entries(inputs || {}).map(([id, amount]) => `${amount} ${itemLabel(id)}`).join(' + ');
            fill(craftSelect, gameContent.recipes, (id, recipe) => `${recipe.label || id} — ${requirements(recipe.inputs)} → ${(recipe.output?.quantity ?? 0)} ${itemLabel(recipe.output?.resource || '')}`);
            fill(moduleSelect, gameContent.ship_modules, (id, module) => `${module.label || id} — ${requirements(module.inputs)}`);
            document.getElementById('craftButton').disabled = !craftSelect.value || !document.getElementById('characterSelect').value;
            document.getElementById('fitModuleButton').disabled = !moduleSelect.value || !document.getElementById('characterSelect').value;
            logActivity('info', 'Game content loaded', `${Object.keys(gameContent.items).length} items, ${Object.keys(gameContent.recipes).length} recipes, ${Object.keys(gameContent.discoveries).length} discovery types, ${Object.keys(gameContent.ship_modules).length} ship modules from JSON.`);
        } catch (error) {
            craftSelect.replaceChildren(new Option('Content catalog unavailable', ''));
            moduleSelect.replaceChildren(new Option('Content catalog unavailable', ''));
            document.getElementById('craftButton').disabled = true; document.getElementById('fitModuleButton').disabled = true;
            document.getElementById('craftStatus').textContent = 'Game content JSON could not be loaded: ' + error.message;
            document.getElementById('fitModuleStatus').textContent = 'Game content JSON could not be loaded: ' + error.message;
            logActivity('error', 'Game content loading failed', error.stack || error.message);
        }
    }
    loadGameContent();

    const contentCatalogSelect = document.getElementById('contentCatalogSelect');
    const contentJsonEditor = document.getElementById('contentJsonEditor');
    const contentManagementStatus = document.getElementById('contentManagementStatus');
    const contentManagementOutput = document.getElementById('contentManagementOutput');
    const contentEntrySelect = document.getElementById('contentEntrySelect');
    const contentEntryForm = document.getElementById('contentEntryForm');
    let contentRevisions = { items: 1, recipes: 1, discoveries: 1, ship_modules: 1 };
    let loadedContentDocuments = {};
    let contentDraftDocuments = {};
    let contentDraftDirty = { items:false, recipes:false, discoveries:false, ship_modules:false };
    let editingNewContentEntry = false;
    const contentCatalogLabels = { items: 'Items & resources', recipes: 'Crafting recipes', discoveries: 'Discoveries & rewards', ship_modules: 'Ship modules' };
    const contentEntryLabels = { items: 'item', recipes: 'recipe', discoveries: 'discovery', ship_modules: 'ship module' };
    function cloneContent(value) { return JSON.parse(JSON.stringify(value)); }
    function escContent(value) { return String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch])); }
    function currentContentDocument() { return contentDraftDocuments[contentCatalogSelect.value]; }
    function selectedContentDocument() {
        if (document.getElementById('contentAdvancedDetails').open) {
            try { const parsed = JSON.parse(contentJsonEditor.value); if (!parsed || Array.isArray(parsed) || typeof parsed !== 'object') throw new Error('The catalog must be a JSON object.'); return parsed; }
            catch (error) { throw new Error('JSON parse error: ' + error.message); }
        }
        const doc = currentContentDocument();
        if (!doc) throw new Error('Load a content catalog first.');
        return cloneContent(doc);
    }
    async function contentApiRequest(endpoint, body) {
        const response = await fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(body) });
        const data = await response.json();
        contentManagementOutput.textContent = JSON.stringify(data, null, 2);
        if (!response.ok || !data.ok) throw new Error(data.error?.message || 'Content API request failed.');
        return data;
    }
    function contentInput(label, name, value, type='text', attrs='') {
        return `<div class="content-field"><label for="entryField_${name}">${escContent(label)}</label><input id="entryField_${name}" data-field="${escContent(name)}" type="${type}" value="${escContent(value)}" ${attrs}></div>`;
    }
    function contentTextarea(label, name, value) {
        return `<div class="content-field"><label for="entryField_${name}">${escContent(label)}</label><textarea id="entryField_${name}" data-field="${escContent(name)}">${escContent(value)}</textarea></div>`;
    }
    function itemOptions(selected='') {
        const items = contentDraftDocuments.items?.entries || {};
        const options = Object.entries(items).map(([id, item]) => `<option value="${escContent(id)}" ${id===selected?'selected':''}>${escContent(item.label || id)} (${escContent(id)})</option>`).join('');
        return `<option value="">Choose item…</option>${options}`;
    }
    function itemCategoryOptions(selected='') {
        const categories = contentDraftDocuments.item_categories?.entries || {};
        return `<option value="">Choose category…</option>` + Object.entries(categories).map(([id, category]) => `<option value="${escContent(id)}" ${id===selected?'selected':''}>${escContent(category.label || id)} (${escContent(id)})</option>`).join('');
    }
    function quantityRows(inputs={}) {
        const rows = Object.entries(inputs);
        return `<div class="content-field"><label>Required items and quantities</label><div id="contentQuantityRows">${rows.map(([id,qty]) => quantityRow(id,qty)).join('')}</div><button type="button" class="secondary" id="contentAddQuantityButton">+ Add required item</button></div>`;
    }
    function quantityRow(id='', qty=1) {
        return `<div class="content-input-row"><div><label>Item</label><select data-quantity-item>${itemOptions(id)}</select></div><div><label>Quantity</label><input data-quantity-value type="number" min="1" max="1000000" step="1" value="${escContent(qty)}"></div><button type="button" class="secondary" data-remove-quantity aria-label="Remove required item">Remove</button></div>`;
    }
    function bindQuantityRows() {
        document.getElementById('contentAddQuantityButton')?.addEventListener('click', () => {
            document.getElementById('contentQuantityRows').insertAdjacentHTML('beforeend', quantityRow('',1));
            bindRemoveQuantityButtons();
        });
        bindRemoveQuantityButtons();
    }
    function bindRemoveQuantityButtons() {
        document.querySelectorAll('[data-remove-quantity]').forEach(button => { if (button.dataset.bound) return; button.dataset.bound='1'; button.addEventListener('click', () => button.closest('.content-input-row')?.remove()); });
    }
    function renderCharacteristicFields(categoryId, currentValues={}) {
        if (!categoryId) return '<section class="content-characteristics"><h4>Characteristics</h4><p class="field-help">Choose an item category to see its defined characteristics.</p></section>';
        const schema = contentDraftDocuments.item_characteristics?.categories?.[categoryId]?.fields || {};
        const fields = Object.entries(schema);
        if (!fields.length) return '<section class="content-characteristics"><h4>Characteristics</h4><p class="field-help">No characteristics are defined for this category yet. Definitions live in config/content/item-characteristics.json.</p></section>';
        let html = '<section class="content-characteristics"><h4>Characteristics</h4><p class="field-help">These values are descriptive metadata only; they do not alter gameplay mechanics yet.</p><div class="content-field-grid">';
        for (const [fieldId, field] of fields) {
            const value = Object.prototype.hasOwnProperty.call(currentValues, fieldId) ? currentValues[fieldId] : field.default;
            const required = field.required ? 'required' : '';
            const label = `${field.label || fieldId}${field.unit ? ' ('+field.unit+')' : ''}`;
            if (field.type === 'enum') {
                const options = Object.entries(field.options || {}).map(([optionId, optionLabel]) => `<option value="${escContent(optionId)}" ${optionId===value?'selected':''}>${escContent(optionLabel)}</option>`).join('');
                html += `<div class="content-field"><label for="entryCharacteristic_${escContent(fieldId)}">${escContent(label)}</label><select id="entryCharacteristic_${escContent(fieldId)}" data-characteristic="${escContent(fieldId)}" data-characteristic-type="enum" ${required}><option value="">Choose…</option>${options}</select>${field.description?`<p class="field-help">${escContent(field.description)}</p>`:''}</div>`;
            } else if (field.type === 'boolean') {
                html += `<div class="content-field"><label><input type="checkbox" data-characteristic="${escContent(fieldId)}" data-characteristic-type="boolean" ${value?'checked':''} style="width:auto;min-height:0"> ${escContent(label)}</label>${field.description?`<p class="field-help">${escContent(field.description)}</p>`:''}</div>`;
            } else {
                const type = field.type === 'integer' || field.type === 'number' ? 'number' : 'text';
                const step = field.type === 'integer' ? '1' : (field.type === 'number' ? 'any' : '');
                const attrs = `${required} ${field.min !== undefined ? `min="${escContent(field.min)}"` : ''} ${field.max !== undefined ? `max="${escContent(field.max)}"` : ''} ${field.max_length !== undefined ? `maxlength="${escContent(field.max_length)}"` : ''} ${step ? `step="${step}"` : ''}`;
                html += `<div class="content-field"><label for="entryCharacteristic_${escContent(fieldId)}">${escContent(label)}</label><input id="entryCharacteristic_${escContent(fieldId)}" type="${type}" data-characteristic="${escContent(fieldId)}" data-characteristic-type="${escContent(field.type)}" value="${escContent(value ?? '')}" ${attrs}>${field.description?`<p class="field-help">${escContent(field.description)}</p>`:''}</div>`;
            }
        }
        return html + '</div></section>';
    }
    function readCharacteristicValues() {
        const values = {};
        document.querySelectorAll('[data-characteristic]').forEach(control => {
            const key = control.dataset.characteristic;
            const type = control.dataset.characteristicType;
            if (type === 'boolean') { values[key] = !!control.checked; return; }
            const raw = control.value;
            if (raw === '') return;
            if (type === 'integer') {
                if (!Number.isSafeInteger(Number(raw))) throw new Error(`Characteristic ${key} must be a whole number.`);
                values[key] = Number(raw);
            } else if (type === 'number') {
                if (!Number.isFinite(Number(raw))) throw new Error(`Characteristic ${key} must be a number.`);
                values[key] = Number(raw);
            } else values[key] = raw;
        });
        return values;
    }
    function renderContentEntry(id) {
        const catalog = contentCatalogSelect.value;
        const doc = currentContentDocument();
        if (!doc) return;
        editingNewContentEntry = id === '__new__';
        const entry = editingNewContentEntry ? {} : cloneContent(doc.entries?.[id] || {});
        document.getElementById('contentEntryHeading').textContent = editingNewContentEntry ? `New ${contentEntryLabels[catalog]}` : (entry.label || id);
        let html = '';
        if (editingNewContentEntry) html += contentInput('Stable ID (lowercase letters, numbers, underscores)', 'id', '', 'text', 'pattern="[a-z][a-z0-9_]{1,63}" maxlength="64" required');
        else html += `<p class="field-help">Stable ID: <code>${escContent(id)}</code>. Existing IDs cannot be renamed here.</p>`;
        html += `<div class="content-field-grid">${contentInput('Display name', 'label', entry.label || '')}`;
        if (catalog === 'items') {
            html += `<div class="content-field"><label for="entryField_category">Category</label><select id="entryField_category" data-field="category" required>${itemCategoryOptions(entry.category || '')}</select><p class="field-help">Categories are defined in config/content/item-categories.json. Choose an approved category; arbitrary text is not accepted.</p></div>`;
            html += `</div>${contentTextarea('Description', 'description', entry.description || '')}<div class="content-field"><label><input data-field="stackable" type="checkbox" ${entry.stackable ? 'checked':''} style="width:auto;min-height:0"> Stackable inventory item</label></div>${renderCharacteristicFields(entry.category || Object.keys(contentDraftDocuments.item_categories?.entries || {})[0] || '', entry.characteristics || {})}`;
        } else if (catalog === 'recipes') {
            html += `</div>${contentTextarea('Description', 'description', entry.description || '')}${quantityRows(entry.inputs || {})}<div class="content-field-grid"><div class="content-field"><label for="entryField_output_resource">Output item</label><select id="entryField_output_resource" data-field="output_resource">${itemOptions(entry.output?.resource || '')}</select></div>${contentInput('Output quantity', 'output_quantity', entry.output?.quantity ?? 1, 'number', 'min="1" max="1000000" step="1"')}</div>`;
        } else if (catalog === 'discoveries') {
            html += `</div>${contentTextarea('Signal description', 'description', entry.description || '')}${contentTextarea('Investigation outcome text', 'outcome', entry.outcome || '')}<div class="content-reward-grid"><div class="content-field"><label for="entryField_reward_resource">Reward item</label><select id="entryField_reward_resource" data-field="reward_resource">${itemOptions(entry.reward?.resource || '')}</select></div>${contentInput('Minimum reward', 'reward_minimum', entry.reward?.minimum ?? 1, 'number', 'min="1" max="1000000" step="1"')}${contentInput('Maximum reward', 'reward_maximum', entry.reward?.maximum ?? 1, 'number', 'min="1" max="1000000" step="1"')}</div>`;
        } else {
            html += contentInput('Fitting slot', 'slot', entry.slot || 'utility');
            html += `</div>${contentTextarea('Description', 'description', entry.description || '')}${quantityRows(entry.inputs || {})}`;
        }
        contentEntryForm.innerHTML = html;
        if (catalog === 'items') {
            document.getElementById('entryField_category')?.addEventListener('change', event => {
                const currentValues = readCharacteristicValues();
                const host = document.querySelector('.content-characteristics');
                if (host) host.outerHTML = renderCharacteristicFields(event.target.value, currentValues);
            });
        }
        bindQuantityRows();
        document.getElementById('contentApplyEntryButton').disabled = false;
        document.getElementById('contentDeleteEntryButton').disabled = editingNewContentEntry;
    }
    function refreshContentEntryList(preferredId='') {
        const doc = currentContentDocument();
        const entries = doc?.entries || {};
        contentEntrySelect.replaceChildren();
        Object.entries(entries).sort((a,b) => String(a[1]?.label || a[0]).localeCompare(String(b[1]?.label || b[0]))).forEach(([id,entry]) => contentEntrySelect.add(new Option(`${entry.label || id} — ${id}`, id)));
        if (preferredId === '__new__') { contentEntrySelect.add(new Option('+ New entry (draft)', '__new__')); contentEntrySelect.value='__new__'; }
        else if (preferredId && entries[preferredId]) contentEntrySelect.value=preferredId;
        else if (contentEntrySelect.options.length) contentEntrySelect.selectedIndex=0;
        if (contentEntrySelect.value) renderContentEntry(contentEntrySelect.value);
        else { contentEntryForm.innerHTML='<p>No entries in this catalog yet. Choose “New entry” to add one.</p>'; document.getElementById('contentEntryHeading').textContent='Entry editor'; document.getElementById('contentDeleteEntryButton').disabled=true; }
        document.getElementById('contentEntryHelp').textContent = `${Object.keys(entries).length} entries in ${contentCatalogLabels[contentCatalogSelect.value]}. Changes are drafts until the catalog is saved.`;
    }
    function applyContentEntry() {
        const catalog = contentCatalogSelect.value;
        const doc = currentContentDocument();
        if (!doc) throw new Error('Load a content catalog first.');
        const read = name => document.querySelector(`[data-field="${name}"]`);
        const value = name => read(name)?.value ?? '';
        const integer = (name,label) => { const raw=value(name); if (raw.trim()==='' || !Number.isSafeInteger(Number(raw)) || Number(raw)<1 || Number(raw)>1000000) throw new Error(`${label} must be a whole number from 1 to 1,000,000.`); return Number(raw); };
        const idField = read('id');
        const id = editingNewContentEntry ? String(idField?.value || '').trim() : contentEntrySelect.value;
        if (!/^[a-z][a-z0-9_]{1,63}$/.test(id)) throw new Error('Entry ID must start with a lowercase letter and contain 2–64 lowercase letters, numbers, or underscores.');
        if (editingNewContentEntry && Object.prototype.hasOwnProperty.call(doc.entries,id)) throw new Error(`ID “${id}” already exists. Choose a unique ID.`);
        const entry = { label: String(value('label')).trim() };
        if (!entry.label) throw new Error('Display name is required.');
        if (catalog === 'items') {
            entry.category=String(value('category')).trim(); entry.description=value('description'); entry.stackable=!!read('stackable')?.checked; entry.characteristics=readCharacteristicValues();
        } else if (catalog === 'recipes') {
            entry.description=value('description'); entry.inputs=collectQuantityRows(); entry.output={resource:value('output_resource'),quantity:integer('output_quantity','Output quantity')};
        } else if (catalog === 'discoveries') {
            entry.description=value('description'); entry.outcome=value('outcome'); entry.reward={resource:value('reward_resource'),minimum:integer('reward_minimum','Minimum reward'),maximum:integer('reward_maximum','Maximum reward')};
        } else {
            entry.slot=String(value('slot')).trim(); entry.description=value('description'); entry.inputs=collectQuantityRows();
        }
        doc.entries[id]=entry;
        contentDraftDocuments[catalog]=doc;
        contentDraftDirty[catalog]=true;
        contentJsonEditor.value=JSON.stringify(doc,null,2);
        refreshContentEntryList(id);
        contentManagementStatus.className='status info';
        contentManagementStatus.textContent=`Draft updated: ${entry.label} (${id}). Validate or save the catalog to commit it to disk.`;
        logActivity('info','Content entry draft updated',`${catalog}/${id}`);
    }
    function collectQuantityRows() {
        const inputs={};
        document.querySelectorAll('#contentQuantityRows .content-input-row').forEach(row => {
            const id=row.querySelector('[data-quantity-item]')?.value || '';
            const raw=row.querySelector('[data-quantity-value]')?.value || '';
            if (!id) throw new Error('Choose an item for every ingredient row, or remove the empty row.');
            if (!Number.isSafeInteger(Number(raw)) || Number(raw)<1 || Number(raw)>1000000) throw new Error('Ingredient quantities must be whole numbers from 1 to 1,000,000.');
            if (Object.prototype.hasOwnProperty.call(inputs,id)) throw new Error(`Item “${id}” appears more than once. Combine its quantities into one row.`);
            inputs[id]=Number(raw);
        });
        if (!Object.keys(inputs).length) throw new Error('At least one required item is needed.');
        return inputs;
    }
    async function loadContentEditor() {
        const catalog=contentCatalogSelect.value;
        contentManagementStatus.className='status'; contentManagementStatus.textContent=`Loading ${contentCatalogLabels[catalog]}…`;
        try {
            const response=await fetch('api/content.php',{headers:{'Accept':'application/json'},cache:'no-store'});
            const data=await response.json();
            if (!response.ok || !data.ok || !data.content?.[catalog]) throw new Error(data.error?.message || 'Could not load content catalogs.');
            loadedContentDocuments=data.content; contentDraftDocuments=cloneContent(data.content);
            contentDraftDirty={ items:false, recipes:false, discoveries:false, ship_modules:false };
            for (const key of Object.keys(contentRevisions)) contentRevisions[key]=Number.isInteger(data.content[key]?.revision)?data.content[key].revision:1;
            for (const key of Object.keys(contentDraftDocuments)) delete contentDraftDocuments[key].catalog;
            contentJsonEditor.value=JSON.stringify(contentDraftDocuments[catalog],null,2);
            refreshContentEntryList();
            contentManagementStatus.className='status success'; contentManagementStatus.textContent=`Loaded ${contentCatalogLabels[catalog]} · revision ${contentRevisions[catalog]} · ${Object.keys(contentDraftDocuments[catalog].entries||{}).length} entries.`;
            logActivity('info','Content catalog loaded',catalog+' revision '+contentRevisions[catalog]);
        } catch(error) { contentManagementStatus.className='status error'; contentManagementStatus.textContent='Load failed: '+error.message; logActivity('error','Content catalog load failed',error.message); }
    }
    async function validateContentDraft() {
        try {
            const catalog=contentCatalogSelect.value;
            if (!document.getElementById('contentAdvancedDetails').open) {
                if (!editingNewContentEntry && contentEntrySelect.value && contentEntrySelect.value !== '__new__') applyContentEntry();
                else if (editingNewContentEntry) applyContentEntry();
            } else {
                const parsed=selectedContentDocument(); contentDraftDocuments[catalog]=parsed; contentDraftDirty[catalog]=true;
            }
            contentManagementStatus.className='status'; contentManagementStatus.textContent='Validating all content references…';
            const data=await contentApiRequest('api/content-validate.php',{catalog,document:selectedContentDocument()});
            contentManagementStatus.className='status success'; contentManagementStatus.textContent=`VALID — ${data.validation.entry_count} entries; all cross-catalog references passed.`;
            logActivity('info','Content draft validated',catalog+': '+data.validation.entry_count+' entries');
            return true;
        } catch(error) { contentManagementStatus.className='status error'; contentManagementStatus.textContent='Validation failed: '+error.message; logActivity('error','Content draft validation failed',error.message); return false; }
    }
    document.getElementById('contentLoadButton').addEventListener('click',()=>{
        if (Object.values(contentDraftDirty).some(Boolean) && !confirm('Reload all catalogs from the server? This will discard unsaved content drafts.')) return;
        loadContentEditor();
    });
    contentCatalogSelect.addEventListener('change',()=>{
        const catalog=contentCatalogSelect.value;
        if (contentDraftDocuments[catalog]) { contentJsonEditor.value=JSON.stringify(contentDraftDocuments[catalog],null,2); refreshContentEntryList(); contentManagementStatus.className='status info'; contentManagementStatus.textContent=`Switched to ${contentCatalogLabels[catalog]}. Unsaved drafts in other catalogs remain in memory.`; }
        else loadContentEditor();
    });
    contentEntrySelect.addEventListener('change',()=>renderContentEntry(contentEntrySelect.value));
    document.getElementById('contentAddEntryButton').addEventListener('click',()=>{ refreshContentEntryList('__new__'); document.getElementById('entryField_id')?.focus(); });
    document.getElementById('contentApplyEntryButton').addEventListener('click',()=>{ try { applyContentEntry(); } catch(error) { contentManagementStatus.className='status error'; contentManagementStatus.textContent='Entry not applied: '+error.message; logActivity('error','Content entry apply failed',error.message); } });
    document.getElementById('contentDeleteEntryButton').addEventListener('click',()=>{
        const catalog=contentCatalogSelect.value, id=contentEntrySelect.value, doc=currentContentDocument();
        if (!id || id==='__new__' || !doc?.entries?.[id]) return;
        const label=doc.entries[id].label || id;
        if (!confirm(`Remove “${label}” (${id}) from this draft? The full catalog must still pass validation before it can be saved.`)) return;
        delete doc.entries[id]; contentDraftDocuments[catalog]=doc; contentDraftDirty[catalog]=true; contentJsonEditor.value=JSON.stringify(doc,null,2); refreshContentEntryList();
        contentManagementStatus.className='status info'; contentManagementStatus.textContent=`Removed ${id} from the draft. Validate and save to commit the change.`;
    });
    document.getElementById('contentValidateButton').addEventListener('click',validateContentDraft);
    document.getElementById('contentSaveButton').addEventListener('click',async()=>{
        if (!await validateContentDraft()) return;
        const catalog=contentCatalogSelect.value;
        try {
            contentManagementStatus.className='status'; contentManagementStatus.textContent='Saving validated catalog with revision check…';
            const data=await contentApiRequest('api/content-save.php',{catalog,expected_revision:contentRevisions[catalog],document:selectedContentDocument()});
            const savedDocument=selectedContentDocument();
            savedDocument.revision=data.saved.revision;
            if (data.saved.updated_at) savedDocument.updated_at=data.saved.updated_at;
            contentRevisions[catalog]=data.saved.revision;
            contentDraftDocuments[catalog]=savedDocument;
            contentDraftDirty[catalog]=false;
            contentJsonEditor.value=JSON.stringify(savedDocument,null,2);
            refreshContentEntryList(contentEntrySelect.value);
            await loadGameContent();
            contentManagementStatus.className='status success'; contentManagementStatus.textContent=`SAVED — ${catalog} revision ${data.saved.revision}, ${data.saved.entry_count} entries. Previous version backed up as ${data.saved.backup_file}. Other catalog drafts were preserved.`;
            logActivity('info','Content catalog saved',catalog+' revision '+data.saved.revision+'; backup '+data.saved.backup_file);
        } catch(error) { contentManagementStatus.className='status error'; contentManagementStatus.textContent='Save failed: '+error.message; logActivity('error','Content catalog save failed',error.message); }
    });
    document.getElementById('contentUseJsonButton').addEventListener('click',()=>{
        try { const catalog=contentCatalogSelect.value, parsed=JSON.parse(contentJsonEditor.value); if(!parsed || Array.isArray(parsed) || typeof parsed!=='object') throw new Error('Catalog must be a JSON object.'); contentDraftDocuments[catalog]=parsed; contentDraftDirty[catalog]=true; refreshContentEntryList(); contentManagementStatus.className='status info'; contentManagementStatus.textContent='Advanced JSON draft loaded into the structured editor. Review entries and validate before saving.'; document.getElementById('contentAdvancedDetails').open=false; }
        catch(error) { contentManagementStatus.className='status error'; contentManagementStatus.textContent='Could not use JSON draft: '+error.message; }
    });
    document.getElementById('contentRefreshJsonButton').addEventListener('click',()=>{ contentJsonEditor.value=JSON.stringify(currentContentDocument(),null,2); contentManagementStatus.className='status info'; contentManagementStatus.textContent='Raw JSON refreshed from the current structured draft.'; });
    loadContentEditor();

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
            document.getElementById('characterLifecycleStatus').textContent = 'Select a character to view its enabled/locked state.';
            for (const id of ['disableCharacterButton','enableCharacterButton','lockCharacterButton','unlockCharacterButton']) document.getElementById(id).disabled = true;
            document.getElementById('moveCharacterButton').disabled = true;
            document.getElementById('planRouteButton').disabled = true;
            document.getElementById('scanSystemButton').disabled = true;
            document.getElementById('craftButton').disabled = true;
            document.getElementById('fitModuleButton').disabled = true;
            document.getElementById('characterScanStatus').textContent = 'Select a character to scan its current system.';
            document.getElementById('characterScanResults').innerHTML = '<div><h4>Current-system scan</h4><p class="dashboard-list">No scan loaded.</p></div>';
            document.getElementById('characterInventory').innerHTML = '<div><h4>Inventory</h4><p class="dashboard-list">Select a character to view recovered resources.</p></div>';
            document.getElementById('craftStatus').textContent = 'Select a character and collect materials to use the crafting bench.';
            document.getElementById('fitModuleStatus').textContent = 'Collect materials and select a character to install ship modules.';
            document.getElementById('shipFittingPanel').innerHTML = '<div><h4>Installed modules</h4><p class="dashboard-list">Select a character to view ship fittings.</p></div>';
            status.textContent = 'Select a character to view its location and exploration history.';
            document.getElementById('travelProgressStatus').textContent = 'Select a character before starting or continuing travel.';
            document.getElementById('startTravelButton').disabled = true;
            document.getElementById('advanceTravelButton').disabled = true;
            return;
        }
        const names = new Map(systems.map(system => [system.id, system.name]));
        renderCharacterDashboard(character);
        const lifecycle = character.lifecycle || { enabled: true, locked: false };
        const isEnabled = lifecycle.enabled !== false;
        const isLocked = lifecycle.locked === true;
        const universeEnabled = currentUniverseLifecycle.enabled !== false;
        const universeLocked = currentUniverseLifecycle.locked === true;
        const canPlay = isEnabled && !isLocked && universeEnabled && !universeLocked;
        document.getElementById('characterLifecycleStatus').textContent = 'State: ' + (isEnabled ? 'enabled' : 'DISABLED') + ' · ' + (isLocked ? 'LOCKED' : 'unlocked') + (canPlay ? ' — gameplay actions available.' : ' — gameplay mutations are blocked until both character and universe are enabled and unlocked.');
        document.getElementById('disableCharacterButton').disabled = !isEnabled;
        document.getElementById('enableCharacterButton').disabled = isEnabled;
        document.getElementById('lockCharacterButton').disabled = isLocked;
        document.getElementById('unlockCharacterButton').disabled = !isLocked;
        document.getElementById('moveCharacterButton').disabled = !canPlay;
        document.getElementById('startTravelButton').disabled = !canPlay;
        document.getElementById('advanceTravelButton').disabled = !canPlay;
        document.getElementById('planRouteButton').disabled = !canPlay;
        document.getElementById('scanSystemButton').disabled = !canPlay;
        document.getElementById('craftButton').disabled = !canPlay;
        document.getElementById('fitModuleButton').disabled = !canPlay;
        const currentId = character.current_system_id || character.location?.system_id || '';
        const scanRecords = character.scans?.systems || {};
        const currentScan = scanRecords[currentId] || null;
        const inventoryHost = document.getElementById('characterInventory');
        inventoryHost.replaceChildren();
        const inventory = character.inventory || {};
        const resources = inventory.resources || {};
        const inventoryPanel = document.createElement('div');
        const inventoryHeading = document.createElement('h4'); inventoryHeading.textContent = 'Recovered resources'; inventoryPanel.append(inventoryHeading);
        const resourceEntries = Object.entries(resources).filter(([, quantity]) => Number(quantity) > 0).sort(([a], [b]) => a.localeCompare(b));
        if (resourceEntries.length) {
            const resourceList = document.createElement('ul'); resourceList.className = 'dashboard-list';
            for (const [key, quantity] of resourceEntries) { const item = document.createElement('li'); item.textContent = (gameContent.items[key]?.label || key.replaceAll('_', ' ')) + ': ' + quantity; resourceList.append(item); }
            inventoryPanel.append(resourceList);
        } else { const empty = document.createElement('p'); empty.className = 'dashboard-list'; empty.textContent = 'No resources recovered yet. Investigate a discovery to collect its reward.'; inventoryPanel.append(empty); }
        const ledgerCount = Array.isArray(inventory.ledger) ? inventory.ledger.length : 0;
        const ledgerNote = document.createElement('p'); ledgerNote.className = 'dashboard-list'; ledgerNote.textContent = 'Recorded recovery events: ' + ledgerCount; inventoryPanel.append(ledgerNote);
        inventoryHost.append(inventoryPanel);
        const fittingHost = document.getElementById('shipFittingPanel'); fittingHost.replaceChildren();
        const fittingPanel = document.createElement('div'); const fittingHeading = document.createElement('h4'); fittingHeading.textContent = 'Installed modules'; fittingPanel.append(fittingHeading);
        const fittedModules = character.ship?.modules || {};
        const slotSet = new Set(Object.values(gameContent.ship_modules).map(module => module.slot).filter(Boolean));
        for (const slot of Object.keys(fittedModules)) slotSet.add(slot);
        const fittingList = document.createElement('ul'); fittingList.className = 'dashboard-list';
        for (const slot of Array.from(slotSet).sort()) { const item = document.createElement('li'); const installed = fittedModules[slot]; const slotLabel = slot.replaceAll('_', ' ').replace(/\b\w/g, char => char.toUpperCase()); item.textContent = slotLabel + ': ' + (installed ? installed.label + ' — ' + installed.description : 'Empty'); fittingList.append(item); }
        fittingPanel.append(fittingList); fittingHost.append(fittingPanel);
        document.getElementById('fitModuleButton').disabled = !canPlay;
        document.getElementById('scanSystemButton').disabled = !canPlay;
        document.getElementById('craftButton').disabled = !canPlay;
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
                    if (discovery.reward) { const reward = document.createElement('p'); reward.className = 'dashboard-list'; reward.textContent = 'Reward: +' + discovery.reward.quantity + ' ' + discovery.reward.label; item.append(reward); }
                } else {
                    const investigate = document.createElement('button'); investigate.type = 'button'; investigate.className = 'secondary investigate-discovery'; investigate.dataset.discoveryId = discovery.id || ''; investigate.textContent = 'Investigate signal'; investigate.disabled = !canPlay; item.append(investigate);
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
            advanceButton.disabled = !canPlay;
        } else if (routeProgress && routeProgress.status === 'completed') {
            progressStatus.textContent = 'Last travel route completed at ' + (names.get(currentId) || currentId) + '. Choose a destination and start a new route to travel again.';
            advanceButton.disabled = !canPlay;
        } else if (routeProgress && routeProgress.status === 'cancelled') {
            progressStatus.textContent = 'Previous route was cancelled. Plan and start a new route to continue travelling.';
            advanceButton.disabled = !canPlay;
        } else {
            progressStatus.textContent = 'No travel route is active for this character. Plan a route, then click Start travel along route.';
            advanceButton.disabled = !canPlay;
        }
        document.getElementById('startTravelButton').disabled = !canPlay;
    }
    function populateCharacterChoices() {
        const select = document.getElementById('characterSelect');
        const prior = select.value;
        select.replaceChildren();
        if (!loadedCharacters.length) {
            const option = document.createElement('option'); option.value = ''; option.textContent = 'No characters in this universe'; select.append(option);
            updateCharacterDetails();
            populateSystemChoices();
            updateDeleteCharacterAvailability();
            return;
        }
        for (const character of loadedCharacters) {
            const option = document.createElement('option'); option.value = character.id;
            option.textContent = character.name + ' — ' + character.id + ((character.lifecycle?.enabled ?? true) ? '' : ' [DISABLED]') + (character.lifecycle?.locked ? ' [LOCKED]' : '');
            option.dataset.revision = String(character.revision ?? 0);
            select.append(option);
        }
        if (loadedCharacters.some(character => character.id === prior)) select.value = prior;
        document.getElementById('characterRevision').value = select.selectedOptions[0]?.dataset.revision ?? '0';
        updateCharacterDetails();
        populateSystemChoices();
        updateDeleteCharacterAvailability();
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

    document.getElementById('craftButton').addEventListener('click', async () => {
        const universeId = document.getElementById('characterUniverseId').value.trim();
        const characterId = document.getElementById('characterSelect').value;
        const recipeId = document.getElementById('craftRecipe').value;
        const quantity = Number(document.getElementById('craftQuantity').value);
        const revision = Number(document.getElementById('characterRevision').value);
        const status = document.getElementById('craftStatus');
        if (!universeId || !characterId) { status.textContent = 'Load a universe and select a character before crafting.'; return; }
        if (!Number.isSafeInteger(quantity) || quantity < 1 || quantity > 10) { status.textContent = 'Craft batches must be a whole number from 1 to 10.'; return; }
        if (!Number.isSafeInteger(revision) || revision < 0) { status.textContent = 'Expected character revision must be a non-negative integer.'; return; }
        const button = document.getElementById('craftButton'); button.disabled = true; status.textContent = 'Crafting…';
        try {
            const response = await fetch('api/character-craft.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ universe_id: universeId, character_id: characterId, recipe_id: recipeId, quantity, expected_revision: revision }) });
            const text = await response.text(); let data;
            try { data = JSON.parse(text); } catch { data = { ok: false, error: { message: text || 'Invalid server response' } }; }
            document.getElementById('characterOutput').textContent = JSON.stringify(data, null, 2);
            if (response.ok && data.ok) {
                const recipe = data.recipe;
                status.textContent = 'Crafted ' + recipe.output.quantity + ' ' + recipe.output.label + ' using ' + recipe.label + '. Character revision: ' + data.character.revision + '.';
                logActivity('info', 'Crafting completed', recipe.label + ' × ' + recipe.quantity + '; output ' + recipe.output.quantity + ' ' + recipe.output.label);
                await refreshCharacters(); document.getElementById('characterSelect').value = characterId;
                document.getElementById('characterRevision').value = String(data.character.revision); updateCharacterDetails();
            } else {
                status.textContent = (data.error?.code || 'crafting_failed') + ': ' + (data.error?.message || 'Crafting failed.');
                logActivity('error', 'Crafting rejected', JSON.stringify(data));
                if (response.status === 409) { await refreshCharacters(); document.getElementById('characterSelect').value = characterId; const latest = loadedCharacters.find(item => item.id === characterId); if (latest) document.getElementById('characterRevision').value = String(latest.revision ?? 0); updateCharacterDetails(); }
            }
        } catch (error) { status.textContent = 'Crafting request failed: ' + error.message; logActivity('error', 'Crafting request failed', error.stack || error.message); }
        finally { button.disabled = !document.getElementById('characterSelect').value; }
    });

    document.getElementById('fitModuleButton').addEventListener('click', async () => {
        const universeId = document.getElementById('characterUniverseId').value.trim();
        const characterId = document.getElementById('characterSelect').value;
        const moduleId = document.getElementById('fitModule').value;
        const revision = Number(document.getElementById('characterRevision').value);
        const status = document.getElementById('fitModuleStatus');
        if (!universeId || !characterId) { status.textContent = 'Load a universe and select a character before fitting a module.'; return; }
        if (!Number.isSafeInteger(revision) || revision < 0) { status.textContent = 'Expected character revision must be a non-negative integer.'; return; }
        const button = document.getElementById('fitModuleButton'); button.disabled = true; status.textContent = 'Installing ship module…';
        try {
            const response = await fetch('api/character-fit.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ universe_id: universeId, character_id: characterId, module_id: moduleId, expected_revision: revision }) });
            const text = await response.text(); let data;
            try { data = JSON.parse(text); } catch { data = { ok: false, error: { message: text || 'Invalid server response' } }; }
            document.getElementById('characterOutput').textContent = JSON.stringify(data, null, 2);
            if (response.ok && data.ok) {
                status.textContent = 'Installed ' + data.module.label + ' in the ' + data.module.slot + ' slot. Character revision: ' + data.character.revision + '.';
                logActivity('info', 'Ship module installed', data.module.label + ' (' + data.module.slot + ')');
                await refreshCharacters(); document.getElementById('characterSelect').value = characterId;
                document.getElementById('characterRevision').value = String(data.character.revision); updateCharacterDetails();
            } else {
                status.textContent = (data.error?.code || 'fitting_failed') + ': ' + (data.error?.message || 'Module installation failed.');
                logActivity('error', 'Ship fitting rejected', JSON.stringify(data));
                if (response.status === 409) { await refreshCharacters(); document.getElementById('characterSelect').value = characterId; const latest = loadedCharacters.find(item => item.id === characterId); if (latest) document.getElementById('characterRevision').value = String(latest.revision ?? 0); updateCharacterDetails(); }
            }
        } catch (error) { status.textContent = 'Ship fitting request failed: ' + error.message; logActivity('error', 'Ship fitting request failed', error.stack || error.message); }
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
                status.textContent = data.investigation_status === 'already_investigated' ? 'This discovery was already investigated. Saved result returned; inventory unchanged; revision unchanged at ' + data.character.revision + '.' : 'Investigation complete: ' + (data.discovery.label || data.discovery.id) + '. Recovered +' + (data.reward?.quantity ?? 0) + ' ' + (data.reward?.label || 'resource') + '. Character revision: ' + data.character.revision + '.';
                logActivity('info', 'Discovery ' + data.investigation_status, (data.discovery.label || discoveryId) + ' — ' + (data.discovery.outcome || 'saved') + (data.reward ? '; reward +' + data.reward.quantity + ' ' + data.reward.label : ''));
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

    async function applyCharacterLifecycle(action) {
        const universeId = document.getElementById('characterUniverseId').value.trim();
        const characterId = document.getElementById('characterSelect').value;
        const character = loadedCharacters.find(item => item.id === characterId);
        const status = document.getElementById('characterLifecycleStatus');
        const revision = Number(document.getElementById('characterRevision').value);
        if (!universeId || !character) { status.textContent = 'Load a universe and select a character first.'; return; }
        if (!Number.isSafeInteger(revision) || revision < 0) { status.textContent = 'Expected character revision must be a non-negative integer.'; return; }
        const labels = {disable:'Disable', enable:'Enable', lock:'Lock', unlock:'Unlock'};
        if (!window.confirm(labels[action] + ' character ' + character.name + '? This preserves all character data and can be reversed.')) return;
        const buttons = ['disableCharacterButton','enableCharacterButton','lockCharacterButton','unlockCharacterButton'].map(id => document.getElementById(id));
        buttons.forEach(button => button.disabled = true);
        status.textContent = labels[action] + 'ing character…';
        try {
            const response = await fetch('api/character-lifecycle.php', {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json'}, body:JSON.stringify({universe_id:universeId, character_id:characterId, action, expected_revision:revision})});
            const text = await response.text(); let data;
            try { data = JSON.parse(text); } catch { data = {ok:false,error:{message:text || 'Invalid server response'}}; }
            if (response.ok && data.ok) {
                await refreshCharacters(); selectCharacterIfPresent(characterId);
                const updated = loadedCharacters.find(item => item.id === characterId);
                status.textContent = labels[action] + ' complete. ' + (updated?.lifecycle?.enabled === false ? 'Character disabled' : 'Character enabled') + '; ' + (updated?.lifecycle?.locked === true ? 'locked.' : 'unlocked.');
                logActivity('info', 'Character lifecycle changed', character.name + ': ' + action + ' (' + data.lifecycle_status + ')');
            } else {
                status.textContent = (data.error?.code || 'character_lifecycle_failed') + ': ' + (data.error?.message || 'Lifecycle update failed.');
                logActivity('error', 'Character lifecycle action rejected', JSON.stringify(data));
                if (response.status === 409) { await refreshCharacters(); selectCharacterIfPresent(characterId); }
            }
        } catch (error) {
            status.textContent = 'Character lifecycle request failed. Check the server and activity log.';
            logActivity('error', 'Character lifecycle request failed', error.stack || error.message);
        } finally { updateCharacterDetails(); }
    }
    document.getElementById('disableCharacterButton').addEventListener('click', () => applyCharacterLifecycle('disable'));
    document.getElementById('enableCharacterButton').addEventListener('click', () => applyCharacterLifecycle('enable'));
    document.getElementById('lockCharacterButton').addEventListener('click', () => applyCharacterLifecycle('lock'));
    document.getElementById('unlockCharacterButton').addEventListener('click', () => applyCharacterLifecycle('unlock'));

    const deleteCharacterButton = document.getElementById('deleteCharacterButton');
    function updateDeleteCharacterAvailability() { deleteCharacterButton.disabled = !document.getElementById('characterSelect').value; }
    document.getElementById('characterSelect').addEventListener('change', updateDeleteCharacterAvailability);
    deleteCharacterButton.addEventListener('click', async () => {
        const universeId = document.getElementById('characterUniverseId').value.trim();
        const characterId = document.getElementById('characterSelect').value;
        const character = loadedCharacters.find(item => item.id === characterId);
        const revision = Number(document.getElementById('characterRevision').value);
        const status = document.getElementById('deleteCharacterStatus');
        if (!universeId || !character) { status.textContent = 'Load a universe and select a character first.'; return; }
        if (!Number.isSafeInteger(revision) || revision < 0) { status.textContent = 'Expected character revision must be a non-negative integer.'; return; }
        if (!window.confirm('Permanently delete ' + character.name + '? This removes all character progress, discoveries, inventory, and ship fitting. The universe and other characters will remain. This cannot be undone.')) return;
        const confirmationName = window.prompt('To permanently delete this character, type its exact name: ' + character.name);
        if (confirmationName === null) { status.textContent = 'Deletion cancelled.'; return; }
        if (confirmationName !== character.name) { status.textContent = 'Deletion cancelled: the name did not match exactly.'; logActivity('warning', 'Character deletion cancelled', 'Confirmation name mismatch for ' + character.name); return; }
        deleteCharacterButton.disabled = true; status.textContent = 'Deleting character and its saved progress…';
        try {
            const response = await fetch('api/character-delete.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ universe_id: universeId, character_id: characterId, confirmation_name: confirmationName, expected_revision: revision }) });
            const text = await response.text(); let data;
            try { data = JSON.parse(text); } catch { data = { ok: false, error: { message: text || 'Invalid server response' } }; }
            document.getElementById('characterOutput').textContent = JSON.stringify(data, null, 2);
            if (response.ok && data.ok) {
                status.textContent = 'Character permanently deleted: ' + data.deletion.name + ' (' + data.deletion.character_id + ').';
                logActivity('warning', 'Character permanently deleted', data.deletion.name + ' (' + data.deletion.character_id + ')');
                await refreshCharacters();
                const select = document.getElementById('characterSelect');
                if (select.options.length) select.selectedIndex = 0;
                document.getElementById('characterRevision').value = select.selectedOptions[0]?.dataset.revision ?? '0';
                updateCharacterDetails(); populateSystemChoices(); updateDeleteCharacterAvailability();
            } else {
                status.textContent = (data.error?.code || 'character_delete_failed') + ': ' + (data.error?.message || 'Character deletion failed.');
                logActivity('error', 'Character deletion rejected', JSON.stringify(data));
                if (response.status === 409) { await refreshCharacters(); selectCharacterIfPresent(characterId); }
            }
        } catch (error) { status.textContent = 'Character deletion request failed. Check the server and activity log.'; logActivity('error', 'Character deletion request failed', error.stack || error.message); }
        finally { updateDeleteCharacterAvailability(); }
    });
    function selectCharacterIfPresent(characterId) {
        const select = document.getElementById('characterSelect');
        if (loadedCharacters.some(item => item.id === characterId)) select.value = characterId;
        document.getElementById('characterRevision').value = select.selectedOptions[0]?.dataset.revision ?? '0';
        updateCharacterDetails(); populateSystemChoices();
    }

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

    function updateUniverseLifecycleControls() {
        const hasUniverse = Boolean(currentUniverseId);
        const enabled = currentUniverseLifecycle.enabled !== false;
        const locked = currentUniverseLifecycle.locked === true;
        document.getElementById('disableUniverseButton').disabled = !hasUniverse || !enabled;
        document.getElementById('enableUniverseButton').disabled = !hasUniverse || enabled;
        document.getElementById('lockUniverseButton').disabled = !hasUniverse || locked;
        document.getElementById('unlockUniverseButton').disabled = !hasUniverse || !locked;
        document.getElementById('universeLifecycleRevision').value = String(currentUniverseLifecycle.revision || 0);
        document.getElementById('universeLifecycleStatus').textContent = hasUniverse
            ? 'Universe ' + currentUniverseId + ' — ' + (enabled ? 'enabled' : 'DISABLED') + ' · ' + (locked ? 'LOCKED' : 'unlocked') + (enabled && !locked ? '. Gameplay mutations are available subject to character state.' : '. Gameplay mutations are blocked; saved records are preserved.')
            : 'Create or load a universe to manage its lifecycle.';
    }

    for (const action of ['disable', 'enable', 'lock', 'unlock']) {
        const button = document.getElementById(action + 'UniverseButton');
        button.addEventListener('click', async () => {
            if (!currentUniverseId) return;
            const status = document.getElementById('universeLifecycleStatus');
            button.disabled = true;
            status.textContent = 'Updating universe lifecycle…';
            try {
                const response = await fetch('api/universe-lifecycle.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ universe_id: currentUniverseId, action, expected_revision: Number(currentUniverseLifecycle.revision || 0) }) });
                const text = await response.text();
                let data; try { data = JSON.parse(text); } catch { data = { ok: false, error: { message: text || 'Invalid server response' } }; }
                if (response.ok && data.ok && data.universe) {
                    currentUniverseLifecycle = data.universe.lifecycle || { enabled: true, locked: false, revision: 0 };
                    currentUniverseLifecycle.enabled = currentUniverseLifecycle.enabled !== false;
                    currentUniverseLifecycle.locked = currentUniverseLifecycle.locked === true;
                    currentUniverseLifecycle.revision = Number(currentUniverseLifecycle.revision || 0);
                    updateUniverseLifecycleControls();
                    updateCharacterDetails();
                    status.textContent += ' Last action: ' + action + ' (' + data.lifecycle_status + ').';
                    logActivity('info', 'Universe lifecycle changed', currentUniverseId + ': ' + action + ' (' + data.lifecycle_status + ')');
                } else {
                    status.textContent = (data.error?.code || 'universe_lifecycle_failed') + ': ' + (data.error?.message || 'Lifecycle update failed.');
                    logActivity('error', 'Universe lifecycle action rejected', JSON.stringify(data));
                }
            } catch (error) {
                status.textContent = 'Universe lifecycle request failed. Check the server and activity log.';
                logActivity('error', 'Universe lifecycle request failed', error.stack || error.message);
            } finally { updateUniverseLifecycleControls(); }
        });
    }

    let dataInventoryCache = null;
    let lastCleanupPreview = null;
    function setVisibleStatus(element, message, kind = 'info') {
        element.textContent = message;
        element.classList.remove('error', 'success', 'info');
        element.classList.add(kind);
    }
    function currentCleanupRecords() {
        const scope = document.getElementById('cleanupScope').value;
        return scope === 'character' ? (dataInventoryCache?.characters || []) : (dataInventoryCache?.universes || []);
    }
    function populateCleanupRecords() {
        const scope = document.getElementById('cleanupScope').value;
        const select = document.getElementById('cleanupRecordId');
        const prior = select.value;
        const query = document.getElementById('cleanupRecordSearch').value.trim().toLocaleLowerCase();
        select.replaceChildren();
        const allRecords = currentCleanupRecords();
        const records = allRecords.filter(record => !query || String(record.name || '').toLocaleLowerCase().includes(query) || String(record.id || '').toLocaleLowerCase().includes(query));
        const count = document.getElementById('cleanupRecordCount');
        count.textContent = records.length + ' of ' + allRecords.length + ' matching';
        if (!records.length) {
            select.add(new Option(allRecords.length ? 'No matching records' : 'No ' + (scope === 'character' ? 'characters' : 'universes') + ' found', ''));
        } else {
            select.add(new Option('Choose a record…', ''));
            for (const record of records) select.add(new Option((record.name || record.id) + ' — ' + record.id, record.id));
            if (records.some(record => record.id === prior)) select.value = prior;
            else if (records.length === 1) select.value = records[0].id;
        }
        lastCleanupPreview = null;
        document.getElementById('deleteManagedCharacterButton').disabled = true;
        document.getElementById('deleteManagedUniverseButton').disabled = true;
        document.getElementById('cleanupConfirmationName').value = '';
        document.getElementById('cleanupUniverseConfirmation').value = '';
        document.getElementById('universeConfirmationPhrase').value = 'Generate a fresh preview to show the phrase';
        document.getElementById('cleanupPreviewOutput').textContent = 'Choose a record and preview its cleanup impact.';
        document.getElementById('cleanupPreviewStatus').textContent = 'No preview generated.';
        setVisibleStatus(document.getElementById('cleanupUniverseDeleteStatus'), 'Select a universe above and preview it. The deletion button stays disabled until the exact phrase matches.', 'info');
    }
    function populateManagedUniverseTargets() {
        const select = document.getElementById('managedUniverseTarget');
        const prior = select.value;
        const query = document.getElementById('managedUniverseSearch').value.trim().toLocaleLowerCase();
        const all = dataInventoryCache?.universes || [];
        const matches = all.filter(universe => !query || String(universe.name || '').toLocaleLowerCase().includes(query) || String(universe.id || '').toLocaleLowerCase().includes(query));
        select.replaceChildren();
        select.add(new Option(matches.length ? 'Choose a universe… (' + matches.length + ' matches)' : (all.length ? 'No matching universes' : 'No saved universes'), ''));
        for (const universe of matches) select.add(new Option((universe.name || universe.id) + ' — ' + universe.id, universe.id));
        if (matches.some(universe => universe.id === prior)) select.value = prior;
        else if (matches.length === 1) select.value = matches[0].id;
    }
    async function refreshDataInventory() {
        const status = document.getElementById('dataInventoryStatus');
        const button = document.getElementById('refreshDataInventoryButton');
        button.disabled = true; status.textContent = 'Reading saved JSON records…';
        try {
            const response = await fetch('api/data-management.php', { headers: { 'Accept': 'application/json' } });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.error?.message || 'Inventory request failed.');
            dataInventoryCache = data.inventory;
            const summary = data.inventory.summary || {};
            document.getElementById('dataUniverseCount').textContent = String(summary.universes ?? 0);
            document.getElementById('dataCharacterCount').textContent = String(summary.characters ?? 0);
            document.getElementById('dataSystemCount').textContent = String(summary.systems ?? 0);
            document.getElementById('dataEventCount').textContent = String(summary.events ?? 0);
            const universeList = document.getElementById('dataUniverseList'); universeList.replaceChildren();
            if (!(data.inventory.universes || []).length) universeList.textContent = 'No saved universes.';
            for (const universe of (data.inventory.universes || [])) {
                const item = document.createElement('p'); item.className = 'dashboard-list';
                item.textContent = universe.name + ' (' + universe.id + ') — ' + universe.systems + ' systems, ' + universe.events + ' events, ' + universe.characters + ' character(s) · ' + ((universe.lifecycle?.enabled ?? true) ? 'enabled' : 'disabled') + (universe.lifecycle?.locked ? ' / locked' : '');
                universeList.append(item);
            }
            const characterList = document.getElementById('dataCharacterList'); characterList.replaceChildren();
            if (!(data.inventory.characters || []).length) characterList.textContent = 'No saved characters.';
            for (const character of (data.inventory.characters || [])) {
                const item = document.createElement('p'); item.className = 'dashboard-list';
                item.textContent = character.name + ' (' + character.id + ') — universe ' + character.universe_id + ', revision ' + character.revision + ', ' + character.discoveries + ' discoveries, ' + character.inventory_stacks + ' resource type(s) · ' + ((character.lifecycle?.enabled ?? true) ? 'enabled' : 'disabled') + (character.lifecycle?.locked ? ' / locked' : '');
                characterList.append(item);
            }
            const backupUniverse = document.getElementById('backupUniverseId'); const selectedBackupUniverse = backupUniverse.value; backupUniverse.replaceChildren();
            if (!(data.inventory.universes || []).length) backupUniverse.add(new Option('No saved universes', ''));
            for (const universe of (data.inventory.universes || [])) backupUniverse.add(new Option((universe.name || universe.id) + ' — ' + universe.id, universe.id));
            if (selectedBackupUniverse) backupUniverse.value = selectedBackupUniverse;
            populateCleanupRecords();
            populateManagedUniverseTargets();
            setVisibleStatus(status, 'Inventory refreshed at ' + new Date(data.inventory.generated_at).toLocaleString() + '. No data was changed.', 'success');
            logActivity('info', 'Saved-data inventory refreshed', summary.universes + ' universe(s), ' + summary.characters + ' character(s).');
        } catch (error) {
            setVisibleStatus(status, 'Could not load inventory: ' + error.message, 'error');
            logActivity('error', 'Saved-data inventory failed', error.stack || error.message);
        } finally { button.disabled = false; }
    }
    document.getElementById('refreshDataInventoryButton').addEventListener('click', refreshDataInventory);
    document.getElementById('cleanupScope').addEventListener('change', populateCleanupRecords);
    document.getElementById('cleanupRecordSearch').addEventListener('input', populateCleanupRecords);
    document.getElementById('cleanupRecordId').addEventListener('change', () => {
        if (lastCleanupPreview && lastCleanupPreview.id !== document.getElementById('cleanupRecordId').value) {
            lastCleanupPreview = null;
            document.getElementById('deleteManagedCharacterButton').disabled = true;
            document.getElementById('deleteManagedUniverseButton').disabled = true;
            document.getElementById('cleanupUniverseConfirmation').value = '';
            document.getElementById('universeConfirmationPhrase').value = 'Generate a fresh preview to show the phrase';
                setVisibleStatus(document.getElementById('cleanupUniverseDeleteStatus'), 'Selected record changed. Generate a fresh preview before deleting anything.', 'info');
        }
    });
    document.getElementById('managedUniverseSearch').addEventListener('input', populateManagedUniverseTargets);
    document.getElementById('managedUniverseTarget').addEventListener('change', () => {
        const id = document.getElementById('managedUniverseTarget').value;
        const confirmation = document.getElementById('cleanupUniverseConfirmation');
        confirmation.value = '';
        document.getElementById('deleteManagedUniverseButton').disabled = true;
        lastCleanupPreview = null;
        document.getElementById('universeConfirmationPhrase').value = id ? 'Preview universe ' + id + ' to generate the required phrase' : 'Generate a fresh preview to show the phrase';
        setVisibleStatus(document.getElementById('cleanupUniverseDeleteStatus'), id ? 'Selected universe ' + id + '. Preview its deletion to enable the confirmation workflow.' : 'Select a universe above and preview it.', 'info');
    });
    document.getElementById('previewUniverseDeletionButton').addEventListener('click', () => {
        const id = document.getElementById('managedUniverseTarget').value;
        if (!id) {
            setVisibleStatus(document.getElementById('cleanupUniverseDeleteStatus'), 'No universe selected. Refresh inventory, then select a universe by name or ID.', 'error');
            logActivity('warning', 'Universe deletion preview blocked', 'No universe selected.');
            return;
        }
        document.getElementById('cleanupScope').value = 'universe';
        document.getElementById('cleanupRecordSearch').value = '';
        populateCleanupRecords();
        document.getElementById('cleanupRecordId').value = id;
        document.getElementById('previewCleanupButton').click();
    });
    document.getElementById('previewCleanupButton').addEventListener('click', async () => {
        const scope = document.getElementById('cleanupScope').value;
        const id = document.getElementById('cleanupRecordId').value;
        const status = document.getElementById('cleanupPreviewStatus');
        if (!id) { setVisibleStatus(status, 'Choose a saved record first. Use the search field to find it by name or ID.', 'error'); return; }
        const button = document.getElementById('previewCleanupButton'); button.disabled = true; status.textContent = 'Calculating impact…';
        try {
            const response = await fetch('api/data-management-preview.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ scope, id }) });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.error?.message || 'Preview failed.');
            lastCleanupPreview = data.preview;
            document.getElementById('cleanupPreviewOutput').textContent = JSON.stringify(data.preview, null, 2);
            status.textContent = data.preview.executable ? (scope === 'character' ? 'Preview ready. Character deletion is available after exact-name confirmation.' : 'Preview ready. Universe deletion will create a verified backup first and remove the universe plus its associated characters.') : 'Preview ready — execution is intentionally disabled for this scope.';
            status.classList.remove('error');
            document.getElementById('deleteManagedCharacterButton').disabled = !(scope === 'character' && data.preview.executable);
            document.getElementById('deleteManagedUniverseButton').disabled = true;
            document.getElementById('cleanupConfirmationName').value = '';
            document.getElementById('cleanupUniverseConfirmation').value = '';
            document.getElementById('universeConfirmationPhrase').value = scope === 'universe' ? 'DELETE UNIVERSE ' + data.preview.id : 'Generate a fresh preview to show the phrase';
            document.getElementById('cleanupUniverseDeleteStatus').classList.remove('error', 'success');
            if (scope === 'universe' && data.preview.executable) {
                setVisibleStatus(document.getElementById('cleanupUniverseDeleteStatus'), 'Preview ready for ' + data.preview.name + ' (' + data.preview.id + '). Review the impact, copy the phrase if useful, then type it exactly below.', 'info');
                document.getElementById('deleteManagedUniverseButton').disabled = true;
            } else setVisibleStatus(document.getElementById('cleanupUniverseDeleteStatus'), 'Select a universe and generate its deletion preview first.', 'info');
            logActivity('info', 'Cleanup impact preview', scope + ' ' + id + '; executable=' + data.preview.executable);
        } catch (error) {
            lastCleanupPreview = null; document.getElementById('deleteManagedCharacterButton').disabled = true; document.getElementById('deleteManagedUniverseButton').disabled = true;
            setVisibleStatus(status, 'Preview failed: ' + error.message, 'error'); logActivity('error', 'Cleanup preview failed', error.stack || error.message);
            setVisibleStatus(document.getElementById('cleanupUniverseDeleteStatus'), 'Preview failed: ' + error.message, 'error');
        } finally { button.disabled = false; }
    });
    function updateUniverseDeleteConfirmationState() {
        const input = document.getElementById('cleanupUniverseConfirmation');
        const status = document.getElementById('cleanupUniverseDeleteStatus');
        const button = document.getElementById('deleteManagedUniverseButton');
        const preview = lastCleanupPreview;
        const currentId = document.getElementById('cleanupRecordId').value;
        const validPreview = Boolean(preview && preview.scope === 'universe' && preview.executable && preview.id === currentId && document.getElementById('cleanupScope').value === 'universe');
        const expected = validPreview ? 'DELETE UNIVERSE ' + preview.id : '';
        const matches = validPreview && input.value === expected;
        button.disabled = !matches;
        if (!validPreview) {
            setVisibleStatus(status, 'No fresh universe preview is active. Select a universe and preview it before deletion.', 'error');
        } else if (!input.value) {
            setVisibleStatus(status, 'Required phrase: ' + expected + '. Type it exactly to enable deletion.', 'info');
        } else if (!matches) {
            setVisibleStatus(status, 'Confirmation does not match. Required phrase: ' + expected + '. No data has been deleted.', 'error');
        } else {
            setVisibleStatus(status, 'Confirmation matches. Clicking the enabled button will create a verified backup before deletion.', 'success');
        }
    }
    document.getElementById('cleanupUniverseConfirmation').addEventListener('input', updateUniverseDeleteConfirmationState);
    document.getElementById('deleteManagedCharacterButton').addEventListener('click', async () => {
        const id = document.getElementById('cleanupRecordId').value;
        const confirmationName = document.getElementById('cleanupConfirmationName').value;
        const character = dataInventoryCache?.characters?.find(item => item.id === id);
        const status = document.getElementById('cleanupDeleteStatus');
        if (!lastCleanupPreview || lastCleanupPreview.scope !== 'character' || !lastCleanupPreview.executable || !character) { status.textContent = 'Generate a fresh character deletion preview first.'; return; }
        if (confirmationName !== character.name) { status.textContent = 'The confirmation must exactly match the character name.'; return; }
        if (!window.confirm('Permanently delete character "' + character.name + '" and all data embedded in its record? This cannot be undone.')) return;
        const button = document.getElementById('deleteManagedCharacterButton'); button.disabled = true; status.textContent = 'Deleting character…';
        try {
            const response = await fetch('api/character-delete.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ universe_id: character.universe_id, character_id: character.id, confirmation_name: confirmationName, expected_revision: character.revision }) });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.error?.message || 'Character deletion failed. Refresh inventory and preview again.');
            status.textContent = 'Character permanently deleted: ' + character.name + '. Other records were not targeted.';
            document.getElementById('cleanupPreviewOutput').textContent = JSON.stringify(data, null, 2);
            document.getElementById('cleanupConfirmationName').value = ''; lastCleanupPreview = null;
            logActivity('warning', 'Character permanently deleted through Data Management', character.name + ' (' + character.id + ')');
            if (document.getElementById('characterSelect').value === character.id) {
                loadedCharacters = loadedCharacters.filter(item => item.id !== character.id); populateCharacterChoices();
            }
            await refreshDataInventory();
            if (document.getElementById('characterUniverseId').value === character.universe_id) await refreshCharacters();
            status.textContent = 'Character permanently deleted: ' + character.name + '. Inventory refreshed.';
        } catch (error) {
            status.textContent = 'Deletion rejected or failed: ' + error.message;
            logActivity('error', 'Managed character deletion failed', error.stack || error.message);
            await refreshDataInventory();
        }
    });

    document.getElementById('deleteManagedUniverseButton').addEventListener('click', async () => {
        const preview = lastCleanupPreview;
        const confirmation = document.getElementById('cleanupUniverseConfirmation').value;
        const status = document.getElementById('cleanupUniverseDeleteStatus');
        const button = document.getElementById('deleteManagedUniverseButton');
        const expectedPhrase = preview && preview.scope === 'universe' ? 'DELETE UNIVERSE ' + preview.id : '';
        if (!preview || preview.scope !== 'universe' || !preview.executable || preview.id !== document.getElementById('cleanupRecordId').value) {
            setVisibleStatus(status, 'Deletion blocked: generate a fresh preview for the selected universe first.', 'error');
            logActivity('warning', 'Universe deletion blocked', 'Missing or stale universe preview.');
            return;
        }
        if (confirmation !== expectedPhrase) {
            setVisibleStatus(status, 'Deletion blocked: confirmation does not match. Required phrase: ' + expectedPhrase + '. No data has been deleted.', 'error');
            logActivity('warning', 'Universe deletion blocked by confirmation mismatch', preview.id);
            return;
        }
        const impact = preview.impact || {};
        if (!window.confirm('PERMANENTLY delete universe "' + preview.name + '" and ' + (impact.related_character_records || 0) + ' associated character(s)? CORE will create a verified backup first. This removes the live records; the backup is retained for recovery.')) {
            setVisibleStatus(status, 'Deletion cancelled. No data was changed.', 'info');
            return;
        }
        button.disabled = true; setVisibleStatus(status, 'Step 1 of 3: creating and verifying the pre-deletion backup…', 'info');
        logActivity('warning', 'Universe deletion started', preview.id + '; waiting for backup and deletion API.');
        try {
            const response = await fetch('api/data-management-universe-delete.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ universe_id: preview.id, confirmation, expected_state_revision: preview.expected_state_revision, expected_lifecycle_revision: preview.expected_lifecycle_revision, expected_character_ids: preview.expected_character_ids }) });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error((data.error?.code ? data.error.code + ': ' : '') + (data.error?.message || ('HTTP ' + response.status + ' — universe deletion failed.')));
            const result = data.deletion;
            setVisibleStatus(status, 'Deleted and verified. ' + result.deleted_characters + ' associated character(s) removed. Recovery backup retained: ' + result.backup.backup_id, 'success');
            document.getElementById('cleanupPreviewOutput').textContent = JSON.stringify(data, null, 2);
            document.getElementById('cleanupUniverseConfirmation').value = ''; document.getElementById('universeConfirmationPhrase').value = 'Deletion completed; refresh inventory to continue'; lastCleanupPreview = null;
                logActivity('warning', 'Universe and associated characters permanently deleted', result.universe_id + '; backup=' + result.backup.backup_id + '; characters=' + result.deleted_characters);
            if (currentUniverseId === result.universe_id) { currentUniverseId = ''; currentUniverseBaseline = null; currentUniverseState = null; currentUniverseLifecycle = { enabled: true, locked: false, revision: 0 }; updateUniverseLifecycleControls(); }
            loadedCharacters = loadedCharacters.filter(item => item.universe_id !== result.universe_id); populateCharacterChoices();
            await refreshDataInventory(); await refreshBackups();
            setVisibleStatus(status, 'Deleted and verified. ' + result.deleted_characters + ' associated character(s) removed. Recovery backup retained: ' + result.backup.backup_id, 'success');
        } catch (error) {
            lastCleanupPreview = null;
            document.getElementById('deleteManagedUniverseButton').disabled = true;
            setVisibleStatus(status, 'Deletion failed: ' + error.message + ' No further attempt is enabled until you refresh inventory and generate a new preview. Check Operations & QA → Activity Log for details.', 'error');
            document.getElementById('cleanupPreviewOutput').textContent = 'UNIVERSE DELETION FAILED\n' + error.message + '\n\nNo success was reported. Review the retained backup list and server log before retrying.';
            logActivity('error', 'Universe deletion failed', error.stack || error.message);
            await refreshDataInventory(); await refreshBackups();
            setVisibleStatus(status, 'Deletion failed: ' + error.message + ' Generate a fresh preview before retrying. Check Operations & QA → Activity Log, storage/logs/universe-deletion.log, and storage/logs/backups.log for details.', 'error');
        } finally {
            // The confirmation is bound to one exact preview; never leave a stale destructive action enabled.
            button.disabled = true;
        }
    });

    async function refreshBackups() {
        const status = document.getElementById('backupStatus');
        const button = document.getElementById('refreshBackupsButton'); button.disabled = true; status.textContent = 'Reading backup inventory…';
        try {
            const response = await fetch('api/data-management-backups.php', { headers: { 'Accept': 'application/json' } }); const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.error?.message || 'Backup listing failed.');
            const select = document.getElementById('backupRecordId'); select.replaceChildren(); const list = document.getElementById('backupList'); list.replaceChildren();
            if (!data.backups.length) { select.add(new Option('No backups available', '')); list.textContent = 'No backups found.'; }
            for (const backup of data.backups) {
                select.add(new Option(backup.universe_name + ' · ' + backup.created_at + ' · ' + backup.backup_id, backup.backup_id));
                const item = document.createElement('p'); item.className = 'dashboard-list'; item.textContent = backup.universe_name + ' (' + backup.universe_id + ') — ' + backup.character_count + ' character(s), ' + backup.created_at + ', SHA-256 ' + backup.checksum_sha256; list.append(item);
            }
            document.getElementById('restoreUniverseBackupButton').disabled = data.backups.length === 0;
            status.textContent = 'Loaded ' + data.backups.length + ' verified backup record(s).';
        } catch (error) { status.textContent = 'Backup listing failed: ' + error.message; logActivity('error', 'Backup listing failed', error.stack || error.message); }
        finally { button.disabled = false; }
    }
    document.getElementById('createUniverseBackupButton').addEventListener('click', async () => {
        const universeId = document.getElementById('backupUniverseId').value; const status = document.getElementById('backupStatus');
        if (!universeId) { status.textContent = 'Choose a universe first.'; return; }
        const button = document.getElementById('createUniverseBackupButton'); button.disabled = true; status.textContent = 'Creating snapshot…';
        try { const response = await fetch('api/data-management-backups.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ universe_id: universeId }) }); const data = await response.json(); if (!response.ok || !data.ok) throw new Error(data.error?.message || 'Backup failed.'); status.textContent = 'Backup created and checksummed: ' + data.backup.backup_id; logActivity('info', 'Universe backup created', data.backup.backup_id + ' for ' + universeId); await refreshBackups(); }
        catch (error) { status.textContent = 'Backup failed: ' + error.message; logActivity('error', 'Universe backup failed', error.stack || error.message); }
        finally { button.disabled = false; }
    });
    document.getElementById('refreshBackupsButton').addEventListener('click', refreshBackups);
    document.getElementById('restoreUniverseBackupButton').addEventListener('click', async () => {
        const backupId = document.getElementById('backupRecordId').value; const confirmation = document.getElementById('backupRestoreConfirmation').value; const status = document.getElementById('backupStatus');
        if (!backupId) { status.textContent = 'Choose a backup first.'; return; }
        if (confirmation !== 'RESTORE') { status.textContent = 'Type RESTORE exactly to confirm.'; return; }
        if (!window.confirm('Restore this universe snapshot? Existing different records will not be overwritten.')) return;
        const button = document.getElementById('restoreUniverseBackupButton'); button.disabled = true; status.textContent = 'Validating and restoring snapshot…';
        try { const response = await fetch('api/data-management-backups-restore.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ backup_id: backupId, confirmation }) }); const data = await response.json(); if (!response.ok || !data.ok) throw new Error(data.error?.message || 'Restore failed.'); status.textContent = 'Restore verified for ' + data.restore.universe_name + '; ' + data.restore.characters_restored_or_verified + ' character(s) restored or verified.'; document.getElementById('backupRestoreConfirmation').value = ''; logActivity('warning', 'Universe backup restored', backupId); await refreshDataInventory(); }
        catch (error) { status.textContent = 'Restore rejected or failed: ' + error.message; logActivity('error', 'Universe backup restore failed', error.stack || error.message); }
        finally { button.disabled = false; }
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
                currentUniverseId = result.data.universe.id || '';
                currentUniverseLifecycle = result.data.universe.lifecycle || { enabled: true, locked: false, revision: 0 };
                currentUniverseLifecycle.enabled = currentUniverseLifecycle.enabled !== false;
                currentUniverseLifecycle.locked = currentUniverseLifecycle.locked === true;
                currentUniverseLifecycle.revision = Number(currentUniverseLifecycle.revision || 0);
                document.getElementById('universeLifecycleRevision').value = String(currentUniverseLifecycle.revision);
                updateUniverseLifecycleControls();
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
