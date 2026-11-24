import { Client, Room } from "@colyseus/core";
import { isDevelopmentJoinAllowed, parseMultiplayerRuntimeConfig } from "../config/runtime.js";
import { normalizeRoomIdentity, type NormalizedRoomIdentity } from "../contracts/RoomIdentity.js";
import { isPhpGrantAuthorizedForRoom } from "../contracts/RoomAuthorization.js";
import {
  applyMovementStep,
  createMovementState,
  MOVEMENT_MIN_INPUT_INTERVAL_MS,
  MOVEMENT_TICK_MS,
  parseMovementInput,
  stopStaleMovementInput,
  type MovementState,
} from "../contracts/MovementProtocol.js";
import { verifyPhpGrant } from "../auth/token.js";
import type { MultiplayerIdentity } from "../contracts/MultiplayerIdentity.js";

const runtimeConfig = parseMultiplayerRuntimeConfig(process.env);

interface RoomJoinOptions {
  universeId?: unknown;
  systemId?: unknown;
  locationId?: unknown;
  displayName?: unknown;
}

interface DevelopmentAuth { mode: "development"; }
type VerifiedAuth = { mode: "php"; identity: MultiplayerIdentity } | DevelopmentAuth;

interface PlayerPresence {
  sessionId: string;
  displayName: string;
  joinedAt: number;
  authMode: "development" | "php";
  characterId?: string;
  accountId?: string;
  appearanceTint: string;
  appearanceSaturation: number;
  appearanceBrightness: number;
  movement: MovementState;
  positionInitialized: boolean;
}

export class UniverseRoom extends Room {
  private identity!: NormalizedRoomIdentity;
  private readonly playerPresence = new Map<string, PlayerPresence>();
  private lastSimulationAt = 0;

  static async onAuth(token: string, options: RoomJoinOptions): Promise<VerifiedAuth> {
    const requested = normalizeRoomIdentity(options);
    if (isDevelopmentJoinAllowed(token, runtimeConfig)) return { mode: "development" };
    const identity = verifyPhpGrant(token, runtimeConfig.hmacSecret);
    if (!isPhpGrantAuthorizedForRoom(identity, requested)) {
      throw new Error("PHP grant does not authorize the requested universe, system, and location.");
    }
    return { mode: "php", identity };
  }

  onCreate(options: RoomJoinOptions): void {
    this.identity = normalizeRoomIdentity(options);
    this.maxClients = runtimeConfig.roomCapacity;
    this.lastSimulationAt = Date.now();
    this.setMetadata({
      universeId: this.identity.universeId,
      systemId: this.identity.systemId,
      locationId: this.identity.locationId,
      playerCount: 0,
    });
    this.onMessage<unknown>("move", (client, message) => this.receiveMovement(client, message));
    this.onMessage<unknown>("set-position", (client, message) => this.setInitialOrFlightPosition(client, message));
    this.setSimulationInterval(() => this.simulatePlayers(), MOVEMENT_TICK_MS);

    console.info("[CORE Multiplayer] Room created", {
      roomId: this.roomId,
      universeId: this.identity.universeId,
      systemId: this.identity.systemId,
      locationId: this.identity.locationId || null,
    });
  }

  onJoin(client: Client, options: RoomJoinOptions, auth?: VerifiedAuth): void {
    if (!auth || (auth.mode !== "development" && auth.mode !== "php")) {
      throw new Error("Unverified multiplayer identity.");
    }
    const requestedIdentity = normalizeRoomIdentity(options);
    if (!sameRoomIdentity(this.identity, requestedIdentity)) {
      throw new Error("Join request does not match the room's universe, system, and location.");
    }

    const displayName = normalizeDisplayName(options.displayName);
    const player: PlayerPresence = {
      sessionId: client.sessionId,
      displayName,
      joinedAt: Date.now(),
      authMode: auth.mode,
      ...(auth.mode === "php" ? { characterId: auth.identity.characterId, accountId: auth.identity.accountId } : {}),
      appearanceTint: auth.mode === "php" ? normalizeAppearanceTint(auth.identity.appearanceTint) : "#61d8e8",
      appearanceSaturation: auth.mode === "php" ? normalizeAppearanceSaturation(auth.identity.appearanceSaturation) : 100,
      appearanceBrightness: auth.mode === "php" ? normalizeAppearanceBrightness(auth.identity.appearanceBrightness) : 100,
      movement: createMovementState(),
      positionInitialized: false,
    };
    const existingPlayers = [...this.playerPresence.values()].map(playerSnapshot);
    this.playerPresence.set(client.sessionId, player);
    this.send(client, "world-state", { players: [...existingPlayers, playerSnapshot(player)] });
    this.broadcast("player-joined", playerSnapshot(player), { except: client });
    this.updateRoomMetadata();

    console.info("[CORE Multiplayer] Player joined", {
      roomId: this.roomId,
      sessionId: client.sessionId,
      playerCount: this.playerPresence.size,
    });
  }

  onLeave(client: Client): void {
    const player = this.playerPresence.get(client.sessionId);
    this.playerPresence.delete(client.sessionId);
    if (player) this.broadcast("player-left", { sessionId: client.sessionId, characterId: player.characterId ?? null });
    this.updateRoomMetadata();

    console.info("[CORE Multiplayer] Player left", {
      roomId: this.roomId,
      sessionId: client.sessionId,
      playerCount: this.playerPresence.size,
    });
  }

  onDispose(): void {
    this.playerPresence.clear();
    console.info("[CORE Multiplayer] Room disposed", { roomId: this.roomId });
  }

  private setInitialOrFlightPosition(client: Client, value: unknown): void {
    const player = this.playerPresence.get(client.sessionId);
    // Position initialization/flight landing is restricted to PHP-authenticated
    // game clients. Coordinates are finite and bounded; normal movement remains
    // server-simulated from directional input.
    if (!player || player.authMode !== "php" || !value || typeof value !== "object" || Array.isArray(value)) return;
    const candidate = value as Record<string, unknown>;
    const x = Number(candidate.x);
    const z = Number(candidate.z);
    if (!Number.isFinite(x) || !Number.isFinite(z)
      || Math.abs(x) > 100000 || Math.abs(z) > 100000) return;
    if (player.positionInitialized && candidate.reason !== "flight-landing") return;
    player.movement.transform.x = x;
    player.movement.transform.y = 0;
    player.movement.transform.z = z;
    player.movement.transform.rotationY = 0;
    player.movement.input = { forward: 0, strafe: 0, running: false, sequence: player.movement.input.sequence };
    player.movement.lastInputAt = 0;
    player.positionInitialized = true;
    this.broadcast("player-transforms", {
      players: [{
        sessionId: player.sessionId,
        characterId: player.characterId ?? null,
        displayName: player.displayName,
        running: false,
        transform: { ...player.movement.transform },
      }],
      serverTime: Date.now(),
    });
  }

  private receiveMovement(client: Client, value: unknown): void {
    const player = this.playerPresence.get(client.sessionId);
    const input = parseMovementInput(value);
    if (!player || !input || input.sequence <= player.movement.input.sequence) return;
    const now = Date.now();
    if (player.movement.lastInputAt > 0 && now - player.movement.lastInputAt < MOVEMENT_MIN_INPUT_INTERVAL_MS) return;
    player.movement.input = input;
    player.movement.lastInputAt = now;
  }

  private simulatePlayers(): void {
    const now = Date.now();
    const elapsed = Math.min(0.1, Math.max(0, (now - this.lastSimulationAt) / 1000));
    this.lastSimulationAt = now;
    const updates: Array<Record<string, unknown>> = [];
    for (const player of this.playerPresence.values()) {
      stopStaleMovementInput(player.movement, now);
      if (!applyMovementStep(player.movement, elapsed)) continue;
      updates.push({
        sessionId: player.sessionId,
        characterId: player.characterId ?? null,
        displayName: player.displayName,
        running: player.movement.input.running,
        transform: { ...player.movement.transform },
      });
    }
    if (updates.length) this.broadcast("player-transforms", { players: updates, serverTime: now });
  }

  private updateRoomMetadata(): void {
    this.setMetadata({
      universeId: this.identity.universeId,
      systemId: this.identity.systemId,
      locationId: this.identity.locationId,
      playerCount: this.playerPresence.size,
    });
  }
}

function playerSnapshot(player: PlayerPresence): Record<string, unknown> {
  return {
    sessionId: player.sessionId,
    characterId: player.characterId ?? null,
    displayName: player.displayName,
    appearanceTint: player.appearanceTint,
    appearanceSaturation: player.appearanceSaturation,
    appearanceBrightness: player.appearanceBrightness,
    running: player.movement.input.running,
    transform: { ...player.movement.transform },
  };
}

function sameRoomIdentity(left: NormalizedRoomIdentity, right: NormalizedRoomIdentity): boolean {
  return left.universeId === right.universeId
    && left.systemId === right.systemId
    && left.locationId === right.locationId;
}

function normalizeDisplayName(value: unknown): string {
  if (typeof value !== "string") return "Explorer";
  const cleaned = value.replace(/[\u0000-\u001f\u007f]/g, "").trim().slice(0, 32);
  return cleaned || "Explorer";
}

function normalizeAppearanceTint(value: unknown): string {
  return typeof value === "string" && /^#[0-9a-fA-F]{6}$/.test(value) ? value.toLowerCase() : "#61d8e8";
}

function normalizeAppearanceSaturation(value: unknown): number {
  return Number.isInteger(value) && Number(value) >= 0 && Number(value) <= 200 ? Number(value) : 100;
}

function normalizeAppearanceBrightness(value: unknown): number {
  return Number.isInteger(value) && Number(value) >= 50 && Number(value) <= 150 ? Number(value) : 100;
}
