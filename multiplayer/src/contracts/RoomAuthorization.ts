import type { MultiplayerIdentity } from "./MultiplayerIdentity.js";
import type { NormalizedRoomIdentity } from "./RoomIdentity.js";

/** A PHP grant can enter only the exact universe/system/location it names. */
export function isPhpGrantAuthorizedForRoom(
  identity: MultiplayerIdentity,
  requested: NormalizedRoomIdentity,
): boolean {
  return identity.universeId === requested.universeId
    && identity.systemId === requested.systemId
    && (identity.locationId ?? "") === requested.locationId;
}
