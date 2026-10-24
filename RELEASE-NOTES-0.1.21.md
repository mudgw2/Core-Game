# CORE 0.1.21 Release Notes

**Milestone:** System Scanning & Discoveries

### Added
- Current-system scanning through the Workbench and development-only API.
- Deterministic signal types: mineral signature, derelict transmission, spatial anomaly, energy residue, unusual asteroid body, and unidentified beacon.
- Character-persistent scan records and readable discovery descriptions.
- Idempotent repeated scans and optimistic revision checks.
- Four browser QA tests covering persistence, repeat scans, validation boundaries, and UI integration.

### Data shape

Each character stores scans at `scans.systems[system_id]`. A scan contains `system_id`, `system_name`, `scanned_at`, `scan_revision`, and a `discoveries` list. Each discovery has a stable ID, type, label, description, system ID/name, and system classification.

### Safety / compatibility
- Existing character and universe records remain in place; no migration or data deletion is performed.
- API remains restricted to development mode and local/private-network requests.
- Error and rejected-request logging retained.
