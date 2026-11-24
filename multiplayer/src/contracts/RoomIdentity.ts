/**
 * Matchmaking identity for a shared CORE simulation space.
 *
 * Empty locationId identifies a system-level room. A validated generated
 * world ID identifies a shared planetary test-world room.
 */
export interface RoomIdentity {
  universeId: string;
  systemId: string;
  locationId: string | null;
}

export interface NormalizedRoomIdentity {
  universeId: string;
  systemId: string;
  locationId: string;
}

export function normalizeRoomIdentity(value: unknown): NormalizedRoomIdentity {
  if (value === null || typeof value !== "object") {
    throw new Error("A multiplayer room identity is required.");
  }

  const candidate = value as Record<string, unknown>;
  const universeId = normalizeIdentifier(candidate.universeId, "universeId", false);
  const systemId = normalizeIdentifier(candidate.systemId, "systemId", false);
  const locationId = normalizeIdentifier(candidate.locationId, "locationId", true);

  return { universeId, systemId, locationId };
}

export function roomIdentityKey(identity: RoomIdentity): string {
  const normalized = normalizeRoomIdentity(identity);
  return JSON.stringify([normalized.universeId, normalized.systemId, normalized.locationId]);
}

function normalizeIdentifier(value: unknown, field: string, optional: boolean): string {
  if (optional && (value === null || value === undefined || value === "")) return "";
  if (typeof value !== "string") throw new Error(`Multiplayer ${field} must be a string.`);

  const normalized = value.trim();
  if (!normalized && optional) return "";
  if (!normalized || normalized.length > 128 || /[\u0000-\u001f\u007f]/.test(normalized)) {
    throw new Error(`Multiplayer ${field} is invalid.`);
  }
  return normalized;
}
