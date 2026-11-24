export interface MultiplayerIdentity {
  issuer: "core-php";
  audience: "core-colyseus";
  subject: string;
  accountId: string;
  characterId: string;
  appearanceTint?: string;
  appearanceSaturation?: number;
  appearanceBrightness?: number;
  universeId: string;
  universeSeed: string;
  systemId: string;
  locationId: string;
  issuedAt: number;
  expiresAt: number;
  tokenId: string;
}
export const MULTIPLAYER_TOKEN_LIFETIME_SECONDS = 300;
