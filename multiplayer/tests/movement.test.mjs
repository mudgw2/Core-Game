import test from "node:test";
import assert from "node:assert/strict";
import {
  applyMovementStep,
  createMovementState,
  MOVEMENT_RUN_SPEED,
  MOVEMENT_WALK_SPEED,
  parseMovementInput,
  stopStaleMovementInput,
} from "../dist/contracts/MovementProtocol.js";

test("movement accepts only bounded directional intent and a sequence number", () => {
  assert.deepEqual(parseMovementInput({ forward: 1, strafe: -1, running: true, sequence: 4 }), { forward: 1, strafe: -1, running: true, sequence: 4 });
  assert.equal(parseMovementInput({ forward: 2, strafe: 0, running: false, sequence: 4 }), null);
  assert.equal(parseMovementInput({ forward: 0, strafe: 0, running: false, sequence: -1 }), null);
  assert.deepEqual(parseMovementInput({ forward: 0, strafe: 0, running: false, sequence: 1, x: 999999 }), { forward: 0, strafe: 0, running: false, sequence: 1 });
  assert.equal(parseMovementInput([1, 0, 1]), null);
});

test("server map axes keep east-west on X and north-south on Z", () => {
  const east = createMovementState();
  east.input = { forward: 0, strafe: 1, running: false, sequence: 1 };
  assert.equal(applyMovementStep(east, 0.05), true);
  assert.ok(east.transform.x > 0);
  assert.equal(east.transform.z, 0);

  const north = createMovementState();
  north.input = { forward: 1, strafe: 0, running: false, sequence: 1 };
  assert.equal(applyMovementStep(north, 0.05), true);
  assert.equal(north.transform.x, 0);
  assert.ok(north.transform.z > 0);
});

test("server simulation applies the configured walk speed", () => {
  const state = createMovementState();
  state.input = { forward: 1, strafe: 0, running: false, sequence: 1 };
  assert.equal(applyMovementStep(state, 0.05), true);
  assert.equal(state.transform.z, MOVEMENT_WALK_SPEED * 0.05);
  assert.equal(state.transform.x, 0);
});

test("server simulation normalizes diagonal movement and caps elapsed time", () => {
  const state = createMovementState();
  state.input = { forward: 1, strafe: 1, running: true, sequence: 1 };
  applyMovementStep(state, 1);
  const expectedAxisDistance = MOVEMENT_RUN_SPEED * 0.1 / Math.sqrt(2);
  assert.ok(Math.abs(state.transform.x - expectedAxisDistance) < 1e-10);
  assert.ok(Math.abs(state.transform.z - expectedAxisDistance) < 1e-10);
});

test("server simulation enforces world coordinate bounds", () => {
  const state = createMovementState();
  state.transform.x = 100000;
  state.input = { forward: 0, strafe: 1, running: true, sequence: 1 };
  assert.equal(applyMovementStep(state, 0.1), false);
  assert.equal(state.transform.x, 100000);
});

test("server stops stale movement input after a disconnected or inactive client", () => {
  const state = createMovementState();
  state.input = { forward: 1, strafe: 0, running: true, sequence: 8 };
  state.lastInputAt = 1000;
  assert.equal(stopStaleMovementInput(state, 1200), false);
  assert.equal(stopStaleMovementInput(state, 1300), true);
  assert.deepEqual(state.input, { forward: 0, strafe: 0, running: false, sequence: 8 });
  assert.equal(applyMovementStep(state, 0.05), false);
});
