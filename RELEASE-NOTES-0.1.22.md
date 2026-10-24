# CORE 0.1.22 Release Notes

## Travel next-jump control reliability

- The Workbench keeps **Travel next jump** actionable whenever a character is selected.
- Active routes explicitly enable the control.
- If there is no active route or the server rejects the action, the Workbench now reports the reason instead of relying on a disabled button with no click feedback.
- Network/request exceptions are displayed in the travel status and recorded in the Activity Log.
- Added one regression test covering the click handler, enabled state, and visible failure reporting.

## Compatibility and safety

- No changes to the travel service's route rules, persistence format, or revision conflict protection.
- Preserve existing `storage/data/` and `storage/logs/` contents when installing.
