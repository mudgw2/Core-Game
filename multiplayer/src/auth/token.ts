import { createHmac, timingSafeEqual } from "node:crypto";
import type { MultiplayerIdentity } from "../contracts/MultiplayerIdentity.js";

function decodeBase64Url(value: string): Buffer {
  if (!/^[A-Za-z0-9_-]+$/.test(value)) throw new Error("Malformed token encoding.");
  return Buffer.from(value.replace(/-/g, "+").replace(/_/g, "/"), "base64");
}

export function verifyPhpGrant(token: string, secret: string, nowSeconds = Math.floor(Date.now() / 1000)): MultiplayerIdentity {
  if (secret.length < 32) throw new Error("Multiplayer HMAC secret is not configured.");
  if (typeof token !== "string" || token.length > 8192) throw new Error("Invalid multiplayer grant.");
  const parts = token.split(".");
  if (parts.length !== 3) throw new Error("Malformed multiplayer grant.");
  const [headerPart, payloadPart, signaturePart] = parts;
  const header = JSON.parse(decodeBase64Url(headerPart).toString("utf8")) as Record<string, unknown>;
  if (header.alg !== "HS256" || header.typ !== "JWT") throw new Error("Unsupported multiplayer grant.");
  const expected = createHmac("sha256", secret).update(`${headerPart}.${payloadPart}`).digest();
  const actual = decodeBase64Url(signaturePart);
  if (actual.length !== expected.length || !timingSafeEqual(actual, expected)) throw new Error("Invalid multiplayer grant signature.");
  const claims = JSON.parse(decodeBase64Url(payloadPart).toString("utf8")) as MultiplayerIdentity;
  if (claims.issuer !== "core-php" || claims.audience !== "core-colyseus"
    || !claims.accountId || !claims.characterId || claims.subject !== claims.characterId
    || !claims.universeId || !claims.systemId || typeof claims.locationId !== "string" || claims.locationId.length > 128 || !claims.tokenId
    || !Number.isInteger(claims.issuedAt) || !Number.isInteger(claims.expiresAt)
    || claims.expiresAt <= nowSeconds || claims.issuedAt > nowSeconds + 30
    || claims.expiresAt - claims.issuedAt > 300
    || (claims.appearanceTint !== undefined && (typeof claims.appearanceTint !== "string" || !/^#[0-9a-fA-F]{6}$/.test(claims.appearanceTint)))
    || (claims.appearanceSaturation !== undefined && (!Number.isInteger(claims.appearanceSaturation) || claims.appearanceSaturation < 0 || claims.appearanceSaturation > 200))
    || (claims.appearanceBrightness !== undefined && (!Number.isInteger(claims.appearanceBrightness) || claims.appearanceBrightness < 50 || claims.appearanceBrightness > 150))) {
    throw new Error("Multiplayer grant is expired or contains invalid claims.");
  }
  return claims;
}
