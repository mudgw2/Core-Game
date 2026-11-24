import test from "node:test";
import assert from "node:assert/strict";
import { createHmac } from "node:crypto";
import { verifyPhpGrant } from "../dist/auth/token.js";

const secret = "test-secret-with-at-least-thirty-two-characters";
function b64(value) { return Buffer.from(value).toString("base64url"); }
function makeToken(overrides = {}) {
  const now = 1_800_000_000;
  const header = b64(JSON.stringify({ alg: "HS256", typ: "JWT" }));
  const claims = {
    issuer: "core-php", audience: "core-colyseus", subject: "char-1",
    accountId: "acct-1", characterId: "char-1", universeId: "uni-1",
    universeSeed: "seed", systemId: "sys-1", locationId: "", issuedAt: now,
    expiresAt: now + 300, tokenId: "token-1", ...overrides,
  };
  const payload = b64(JSON.stringify(claims));
  const input = `${header}.${payload}`;
  return `${input}.${createHmac("sha256", secret).update(input).digest("base64url")}`;
}

test("valid PHP HMAC grant verifies and returns authoritative identity", () => {
  const identity = verifyPhpGrant(makeToken(), secret, 1_800_000_010);
  assert.equal(identity.characterId, "char-1");
  assert.equal(identity.universeId, "uni-1");
  assert.equal(identity.locationId, "");
});
test("invalid signature is rejected", () => {
  const token = makeToken().replace(/.$/, "A");
  assert.throws(() => verifyPhpGrant(token, secret, 1_800_000_010), /signature/);
});
test("expired PHP grant is rejected", () => {
  assert.throws(() => verifyPhpGrant(makeToken({ expiresAt: 1_799_999_999 }), secret, 1_800_000_000), /expired/);
});
test("grant with mismatched subject or excessive lifetime is rejected", () => {
  assert.throws(() => verifyPhpGrant(makeToken({ subject: "other" }), secret, 1_800_000_010), /invalid claims/);
  assert.throws(() => verifyPhpGrant(makeToken({ expiresAt: 1_800_000_301 }), secret, 1_800_000_010), /invalid claims/);
});

test("grant requires a bounded location identity claim", () => {
  assert.throws(() => verifyPhpGrant(makeToken({ locationId: undefined }), secret, 1_800_000_010), /invalid claims/);
  assert.throws(() => verifyPhpGrant(makeToken({ locationId: "w".repeat(129) }), secret, 1_800_000_010), /invalid claims/);
});


test("valid character appearance settings are carried in the verified identity", () => {
  const identity = verifyPhpGrant(makeToken({ appearanceTint: "#A14BC7", appearanceSaturation: 145, appearanceBrightness: 115 }), secret, 1_800_000_010);
  assert.equal(identity.appearanceTint, "#A14BC7");
  assert.equal(identity.appearanceSaturation, 145);
  assert.equal(identity.appearanceBrightness, 115);
});

test("out-of-range character appearance settings are rejected", () => {
  assert.throws(() => verifyPhpGrant(makeToken({ appearanceSaturation: 201 }), secret, 1_800_000_010), /invalid claims/);
  assert.throws(() => verifyPhpGrant(makeToken({ appearanceBrightness: 151 }), secret, 1_800_000_010), /invalid claims/);
});

test("malformed character appearance tint is rejected", () => {
  assert.throws(() => verifyPhpGrant(makeToken({ appearanceTint: "red;alert(1)" }), secret, 1_800_000_010), /invalid claims/);
});
