import test from "node:test";
import assert from "node:assert/strict";
import { createHmac } from "node:crypto";
import { verifyPhpGrant } from "../dist/auth/token.js";
import { normalizeRoomIdentity } from "../dist/contracts/RoomIdentity.js";
import { isPhpGrantAuthorizedForRoom } from "../dist/contracts/RoomAuthorization.js";

const secret = "room-auth-test-secret-with-at-least-32-characters";
function makeToken(locationId) {
  const now = 1_800_000_000;
  const encode = value => Buffer.from(JSON.stringify(value)).toString("base64url");
  const header = encode({ alg: "HS256", typ: "JWT" });
  const payload = encode({
    issuer: "core-php", audience: "core-colyseus", subject: "char-1",
    accountId: "acct-1", characterId: "char-1", universeId: "uni-1",
    universeSeed: "seed", systemId: "sys-1", locationId, issuedAt: now,
    expiresAt: now + 300, tokenId: "token-1",
  });
  const unsigned = `${header}.${payload}`;
  return `${unsigned}.${createHmac("sha256", secret).update(unsigned).digest("base64url")}`;
}

test("PHP grant authorizes only its exact generated world room", () => {
  const identity = verifyPhpGrant(makeToken("world-1"), secret, 1_800_000_010);
  const authorized = normalizeRoomIdentity({ universeId: "uni-1", systemId: "sys-1", locationId: "world-1" });
  const otherWorld = normalizeRoomIdentity({ universeId: "uni-1", systemId: "sys-1", locationId: "world-2" });
  const otherUniverse = normalizeRoomIdentity({ universeId: "uni-2", systemId: "sys-1", locationId: "world-1" });
  assert.equal(isPhpGrantAuthorizedForRoom(identity, authorized), true);
  assert.equal(isPhpGrantAuthorizedForRoom(identity, otherWorld), false);
  assert.equal(isPhpGrantAuthorizedForRoom(identity, otherUniverse), false);
});
