/**
 * Client-to-server movement intent. Clients submit bounded directional input,
 * never coordinates. The room simulates and publishes authoritative transforms.
 */
export interface MovementInput {
  forward: -1 | 0 | 1;
  strafe: -1 | 0 | 1;
  running: boolean;
  sequence: number;
}

export interface AuthoritativePlayerTransform {
  x: number;
  y: number;
  z: number;
  rotationY: number;
}

export interface MovementState {
  transform: AuthoritativePlayerTransform;
  input: MovementInput;
  lastInputAt: number;
}

export const MOVEMENT_TICK_MS = 50;
export const MOVEMENT_MAX_DELTA_SECONDS = 0.1;
export const MOVEMENT_WALK_SPEED = 3.2;
export const MOVEMENT_RUN_SPEED = 6.0;
// The test-world client maps these bounded local offsets onto a spherical body.
// Keep the limit large enough to traverse multiple planetary circumferences.
export const MOVEMENT_WORLD_LIMIT = 100000;
export const MOVEMENT_MIN_INPUT_INTERVAL_MS = 16;
export const MOVEMENT_INPUT_TIMEOUT_MS = 250;

export function parseMovementInput(value: unknown): MovementInput | null {
  if (!value || typeof value !== "object" || Array.isArray(value)) return null;
  const candidate = value as Record<string, unknown>;
  const { forward, strafe, running, sequence } = candidate;
  if ((forward !== -1 && forward !== 0 && forward !== 1)
    || (strafe !== -1 && strafe !== 0 && strafe !== 1)
    || typeof running !== "boolean"
    || !Number.isSafeInteger(sequence) || (sequence as number) < 0) return null;
  return { forward, strafe, running, sequence: sequence as number };
}

export function createMovementState(): MovementState {
  return {
    transform: { x: 0, y: 0, z: 0, rotationY: 0 },
    input: { forward: 0, strafe: 0, running: false, sequence: -1 },
    lastInputAt: 0,
  };
}

export function stopStaleMovementInput(state: MovementState, nowMilliseconds: number): boolean {
  if (state.lastInputAt <= 0 || nowMilliseconds - state.lastInputAt <= MOVEMENT_INPUT_TIMEOUT_MS) return false;
  state.input = { forward: 0, strafe: 0, running: false, sequence: state.input.sequence };
  state.lastInputAt = 0;
  return true;
}

export function applyMovementStep(state: MovementState, elapsedSeconds: number): boolean {
  const delta = Math.min(MOVEMENT_MAX_DELTA_SECONDS, Math.max(0, elapsedSeconds));
  const { forward, strafe, running } = state.input;
  if (delta <= 0 || (forward === 0 && strafe === 0)) return false;

  let x = strafe;
  let z = forward;
  const magnitude = Math.hypot(x, z);
  if (magnitude > 1) { x /= magnitude; z /= magnitude; }
  const speed = running ? MOVEMENT_RUN_SPEED : MOVEMENT_WALK_SPEED;
  const nextX = Math.max(-MOVEMENT_WORLD_LIMIT, Math.min(MOVEMENT_WORLD_LIMIT, state.transform.x + x * speed * delta));
  const nextZ = Math.max(-MOVEMENT_WORLD_LIMIT, Math.min(MOVEMENT_WORLD_LIMIT, state.transform.z + z * speed * delta));
  const changed = nextX !== state.transform.x || nextZ !== state.transform.z;
  if (!changed) return false;
  state.transform.x = nextX;
  state.transform.z = nextZ;
  state.transform.rotationY = Math.atan2(x, z);
  return true;
}
