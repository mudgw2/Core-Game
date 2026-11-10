<?php
declare(strict_types=1);

$coreReleaseVersion = require __DIR__ . '/config/version.php';

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
    <link rel="stylesheet" href="assets/admin.css?v=0.1.86">
</head>
<body>
<main>
    <header class="app-header">
        <div class="eyebrow">Procedural sci-fi sandbox · Foundation <?= htmlspecialchars($coreReleaseVersion, ENT_QUOTES, 'UTF-8') ?></div>
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
        <button type="button" role="tab" id="tab-accounts" aria-controls="panel-accounts" aria-selected="false" tabindex="-1" data-tab="accounts">Player Accounts</button>
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
            <h2>Character management</h2>
            <p>Backend registry for every saved character, across all universes. Select a record to inspect its status and travel state. Character records are permanent: disable to archive and restore to reactivate. This is separate from the player-facing character roster.</p>
            <div class="character-admin-toolbar"><div><strong id="characterRosterCount">Characters not loaded</strong><span>Saved records · all universes · IDs are never recycled</span></div><div class="actions"><button id="characterViewActiveButton" type="button" aria-pressed="true">Active</button><button id="characterViewArchiveButton" type="button" class="secondary" aria-pressed="false">Archive</button><button id="refreshCharactersButton" type="button">Refresh registry</button></div></div>
            <div id="characterAdminList" class="character-admin-list" aria-label="Saved characters"><p class="character-admin-empty">Select Refresh registry to load saved characters.</p></div>
            <p id="characterArchiveStatus" class="status" role="status" aria-live="polite">Disable a character to archive it. Archived records retain their IDs and complete progress and can be restored.</p>
            <label for="characterUniverseId">Universe ID</label>
            <input id="characterUniverseId" autocomplete="off" placeholder="Filled after Create / Load">
            <label for="characterName">Character name</label>
            <input id="characterName" maxlength="40" autocomplete="off" placeholder="e.g. Mira Voss">
            <label for="characterRole">Role / starting concept</label>
            <input id="characterRole" maxlength="40" value="Explorer" autocomplete="off">
            <div class="actions"><button id="createCharacterButton" type="button">Create character in selected universe</button></div>
            <p id="characterStatus" class="status" aria-live="polite">Select a universe before creating a character.</p>
            <h3>Selected character details &amp; travel operations</h3>
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
            <hr style="margin:24px 0;border-color:#34445f">
            <section id="characteristicManager" aria-labelledby="characteristicManagerTitle">
                <h3 id="characteristicManagerTitle">Item Characteristics Manager</h3>
                <p>Define typed fields for each item category without editing JSON. Existing item values are checked before a schema can be saved. Schema changes are backed up and revision-checked.</p>
                <div class="content-studio-grid">
                    <section>
                        <label for="schemaCategorySelect">Item category</label>
                        <select id="schemaCategorySelect"><option value="">Load definitions first</option></select>
                        <label for="schemaFieldSelect" style="margin-top:12px">Characteristic field</label>
                        <select id="schemaFieldSelect"><option value="">Choose a field</option></select>
                        <div class="actions"><button id="schemaNewFieldButton" type="button" class="secondary">+ New field</button><button id="schemaRemoveFieldButton" type="button" class="secondary">Remove field</button></div>
                        <p class="field-help">Field IDs are stable after creation. Removing or changing a field is blocked if it would invalidate saved item values.</p>
                    </section>
                    <section>
                        <div class="content-field-grid">
                            <div class="content-field"><label for="schemaFieldId">Field ID</label><input id="schemaFieldId" pattern="[a-z][a-z0-9_]{1,63}" maxlength="64" placeholder="e.g. durability" required></div>
                            <div class="content-field"><label for="schemaFieldLabel">Display label</label><input id="schemaFieldLabel" maxlength="100" placeholder="e.g. Durability" required></div>
                            <div class="content-field"><label for="schemaFieldType">Data type</label><select id="schemaFieldType"><option value="text">Text</option><option value="number">Number (decimal allowed)</option><option value="integer">Integer (whole number)</option><option value="boolean">Boolean (yes/no)</option><option value="enum">Enumerated choices</option></select></div>
                            <div class="content-field"><label for="schemaFieldRequired"><input id="schemaFieldRequired" type="checkbox" style="width:auto;min-height:0"> Required value</label></div>
                            <div class="content-field"><label for="schemaFieldDefault">Default value (optional)</label><input id="schemaFieldDefault" maxlength="500" placeholder="Leave blank for no default"></div>
                            <div class="content-field"><label for="schemaFieldMin">Minimum (number/integer)</label><input id="schemaFieldMin" type="number" step="any" placeholder="No minimum"></div>
                            <div class="content-field"><label for="schemaFieldMax">Maximum (number/integer)</label><input id="schemaFieldMax" type="number" step="any" placeholder="No maximum"></div>
                        </div>
                        <div class="content-field"><label for="schemaFieldOptions">Enumerated options (one per line: <code>id=Display label</code>)</label><textarea id="schemaFieldOptions" rows="5" placeholder="common=Common&#10;rare=Rare"></textarea><p class="field-help">Only used for Enumerated choices. Option IDs use lowercase letters, numbers, and underscores.</p></div>
                        <div class="content-field"><label for="schemaFieldDescription">Description / authoring hint</label><textarea id="schemaFieldDescription" rows="3" maxlength="500"></textarea></div>
                        <div class="actions"><button id="schemaApplyFieldButton" type="button">Apply field draft</button></div>
                    </section>
                </div>
                <div class="actions"><button id="schemaReloadButton" type="button" class="secondary">Reload schema</button><button id="schemaValidateButton" type="button" class="secondary">Validate schema &amp; existing items</button><button id="schemaSaveButton" type="button">Save characteristic definitions</button></div>
                <p id="schemaManagerStatus" class="status" role="status" aria-live="polite">Load definitions to begin.</p>
                <pre id="schemaManagerOutput" aria-live="polite">Schema validation and save results appear here.</pre>
            </section>
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
        <section hidden aria-hidden="true">
            <h2>Character retention policy</h2>
            <input id="cleanupConfirmationName"><button id="deleteManagedCharacterButton" type="button" disabled>Permanent character deletion retired</button>
            <p id="cleanupDeleteStatus">Permanent character deletion is disabled.</p>
        </section>
        <section>
            <h2>Permanent universe removal</h2>
            <p>Universe removal must never delete character records. Universes with associated characters cannot be permanently removed; disable or lock the universe instead. Use backups for disaster recovery.</p>
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
    <section class="tab-panel grid" id="panel-accounts" role="tabpanel" aria-labelledby="tab-accounts" data-panel="accounts" hidden>
        <section style="grid-column:1/-1">
            <h2>Player accounts</h2>
            <p>Find an account by email, then open its dedicated detail screen to edit account information or change its access state.</p>
            <div class="actions"><button id="ensureTestAccountButton" type="button">Create / reset test account</button><button id="refreshPlayerAccountsButton" type="button" class="secondary">Refresh</button></div>
            <p class="fine-print">Development-only account administration. Listing and searching are read-only. Creating or resetting the development test account is an explicit action. Do not expose this Workbench or its account API publicly.</p>
            <div class="account-search-row" style="display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:end;margin:16px 0">
              <div><label for="playerAccountSearch">Search accounts by email</label><input id="playerAccountSearch" type="search" autocomplete="off" placeholder="Enter all or part of an email address"></div>
              <button id="searchPlayerAccountsButton" type="button">Search</button>
            </div>
            <p id="playerAccountsStatus" class="status" role="status" aria-live="polite">Load accounts to begin.</p>
            <div class="table-wrap"><table><thead><tr><th>Email</th><th>Type</th><th>Status</th><th>Created</th></tr></thead><tbody id="playerAccountsRows"><tr><td colspan="4">Account list not loaded.</td></tr></tbody></table></div>
            <div class="actions" style="justify-content:space-between;align-items:center;margin-top:12px"><span id="playerAccountsPageInfo" class="status">—</span><div class="actions"><button id="playerAccountsPrevious" class="secondary" type="button" disabled>Previous</button><button id="playerAccountsNext" class="secondary" type="button" disabled>Next</button></div></div>
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
<script src="assets/admin.js?v=0.1.86"></script>
</body>
</html>
