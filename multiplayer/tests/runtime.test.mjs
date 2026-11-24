import test from 'node:test';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import assert from 'node:assert/strict';
import {
  isDevelopmentJoinAllowed,
  parseMultiplayerRuntimeConfig,
} from '../dist/config/runtime.js';
import {
  normalizeRoomIdentity,
  roomIdentityKey,
} from '../dist/contracts/RoomIdentity.js';

const token = 'local-development-token-0123456789';

test('runtime defaults bind to loopback with development joins disabled', () => {
  const config = parseMultiplayerRuntimeConfig({});
  assert.equal(config.host, '127.0.0.1');
  assert.equal(config.port, 2567);
  assert.equal(config.roomCapacity, 16);
  assert.equal(config.developmentGuestsEnabled, false);
});

test('development join requires non-production loopback and a long configured token', () => {
  const config = parseMultiplayerRuntimeConfig({
    NODE_ENV: 'development',
    CORE_MULTIPLAYER_HOST: '127.0.0.1',
    CORE_MULTIPLAYER_ALLOW_DEV_GUESTS: 'true',
    CORE_MULTIPLAYER_DEV_TOKEN: token,
  });
  assert.equal(config.developmentGuestsEnabled, true);
  assert.equal(isDevelopmentJoinAllowed(token, config), true);
  assert.equal(isDevelopmentJoinAllowed(`${token}x`, config), false);
});

test('production and non-loopback hosts cannot enable development guest access', () => {
  const production = parseMultiplayerRuntimeConfig({
    NODE_ENV: 'production',
    CORE_MULTIPLAYER_HOST: '127.0.0.1',
    CORE_MULTIPLAYER_ALLOW_DEV_GUESTS: 'true',
    CORE_MULTIPLAYER_DEV_TOKEN: token,
  });
  const publicHost = parseMultiplayerRuntimeConfig({
    NODE_ENV: 'development',
    CORE_MULTIPLAYER_HOST: '0.0.0.0',
    CORE_MULTIPLAYER_ALLOW_DEV_GUESTS: 'true',
    CORE_MULTIPLAYER_DEV_TOKEN: token,
  });
  assert.equal(production.developmentGuestsEnabled, false);
  assert.equal(publicHost.developmentGuestsEnabled, false);
});

test('room identity normalizes system-level location and keeps tuple keys collision-safe', () => {
  const identity = normalizeRoomIdentity({
    universeId: ' universe-1 ',
    systemId: ' system:2 ',
    locationId: null,
  });
  assert.deepEqual(identity, {
    universeId: 'universe-1',
    systemId: 'system:2',
    locationId: '',
  });
  assert.equal(roomIdentityKey(identity), JSON.stringify(['universe-1', 'system:2', '']));
  assert.notEqual(
    roomIdentityKey({ universeId: 'a:b', systemId: 'c', locationId: null }),
    roomIdentityKey({ universeId: 'a', systemId: 'b:c', locationId: null }),
  );
});

test('room identity rejects missing, oversized, and control-character identifiers', () => {
  assert.throws(() => normalizeRoomIdentity({ universeId: '', systemId: 's' }));
  assert.throws(() => normalizeRoomIdentity({ universeId: 'u', systemId: 's\n1' }));
  assert.throws(() => normalizeRoomIdentity({ universeId: 'u'.repeat(129), systemId: 's' }));
});

test('invalid port and room capacity values are rejected', () => {
  assert.throws(() => parseMultiplayerRuntimeConfig({ CORE_MULTIPLAYER_PORT: '70000' }));
  assert.throws(() => parseMultiplayerRuntimeConfig({ CORE_MULTIPLAYER_ROOM_CAPACITY: '0' }));
});


test('dependency manifest excludes unused Colyseus auth chain and pins patched NanoID floor', () => {
  const here = dirname(fileURLToPath(import.meta.url));
  const packageJson = JSON.parse(readFileSync(resolve(here, '../package.json'), 'utf8'));
  assert.equal(packageJson.dependencies.colyseus, undefined);
  assert.equal(packageJson.dependencies['@colyseus/auth'], undefined);
  assert.match(packageJson.dependencies['@colyseus/core'], /^\^0\.16\./);
  assert.match(packageJson.dependencies['@colyseus/ws-transport'], /^\^0\.16\./);
  assert.equal(packageJson.overrides['@colyseus/core'].nanoid, '^3.3.18');
});
