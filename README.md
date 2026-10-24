# CORE PHP Foundation 0.1.18 — Accessible Movement Choices

This maintenance update improves the character movement interface after route planning was restored.

## Changes

- Movement dropdown now contains only the character's current system and directly connected one-jump destinations. Unavailable destinations are omitted instead of rendered as subtly greyed-out disabled options.
- Selectable options use explicit labels: `[CURRENT SYSTEM]` and `[ONE-JUMP DESTINATION]`.
- High-contrast, persistent help text explains the number of available choices and clearly distinguishes one-jump movement from route planning.
- Route destination dropdown continues to list every system in the universe, including systems several jumps away.
- Stronger select borders and visible keyboard focus outlines improve visual and keyboard accessibility.
- Character selection and creation refresh movement choices so they correspond to the selected character.

## Install

Extract the ZIP and copy the package contents into the existing CORE project directory, overwriting matching application files. Preserve the existing `storage/data/` and `storage/logs/` contents.

Then refresh the Workbench, select a character, and inspect both **Move destination — one jump only** and **Route destination**. The former should show only valid immediate destinations; the latter should show all systems.

## Scope

This is a user-interface accessibility and state-refresh improvement. It does not implement multi-hop travel. Planning a route remains read-only; movement remains one directly connected jump at a time.
